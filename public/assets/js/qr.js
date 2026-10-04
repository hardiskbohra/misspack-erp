/* ==========================================================================
   SHIPQR — dependency-free QR code generator (byte mode, ECC levels L/M)
   --------------------------------------------------------------------------
   Why hand-rolled: the ERP ships without a QR library and the sandbox has no
   package registry, so stickers/tracking links must be encodable locally —
   no CDN, no external service, no server round-trip.

   Scope: QR Code model 2, versions 1..10, byte mode, ECC level M (falls back
   to L when the payload does not fit at M). That covers ~270 characters,
   which is far more than a tracking URL needs.

   Usage:
     window.ShipQR.svg('https://host/track-shipment/abc');   // -> '<svg …>'
     window.ShipQR.matrix('text');                            // -> {size, modules}
   Declarative:
     <div data-ship-qr="https://…" data-ship-qr-size="120"
          data-ship-qr-caption="SHP-000042"></div>
   Elements are filled automatically on DOMContentLoaded.
   ========================================================================== */
(function (global) {
    'use strict';

    /* ---------------- GF(256) arithmetic (primitive polynomial 0x11D) ---------------- */

    var EXP = new Uint8Array(510);
    var LOG = new Uint8Array(256);

    (function () {
        var x = 1;
        for (var i = 0; i < 255; i++) {
            EXP[i] = x;
            LOG[x] = i;
            x <<= 1;
            if (x & 0x100) {
                x ^= 0x11d;
            }
        }
        for (var j = 255; j < EXP.length; j++) {
            EXP[j] = EXP[j - 255];
        }
    })();

    function gfMul(a, b) {
        if (a === 0 || b === 0) {
            return 0;
        }

        return EXP[LOG[a] + LOG[b]];
    }

    /* Generator polynomial of the Reed-Solomon code, without the leading 1. */
    function rsDivisor(degree) {
        var result = [];
        var i;

        for (i = 0; i < degree - 1; i++) {
            result.push(0);
        }
        result.push(1);

        var root = 1;
        for (i = 0; i < degree; i++) {
            for (var j = 0; j < result.length; j++) {
                result[j] = gfMul(result[j], root);
                if (j + 1 < result.length) {
                    result[j] ^= result[j + 1];
                }
            }
            root = gfMul(root, 0x02);
        }

        return result;
    }

    function rsRemainder(data, divisor) {
        var result = [];
        var i;

        for (i = 0; i < divisor.length; i++) {
            result.push(0);
        }

        for (i = 0; i < data.length; i++) {
            var factor = data[i] ^ result.shift();
            result.push(0);
            for (var j = 0; j < divisor.length; j++) {
                result[j] ^= gfMul(divisor[j], factor);
            }
        }

        return result;
    }

    /* ---------------- version tables ---------------- */

    /* version -> [eccPerBlock, blocks1, dataPerBlock1, blocks2, dataPerBlock2] */
    var BLOCKS = {
        L: {
            1: [7, 1, 19, 0, 0], 2: [10, 1, 34, 0, 0], 3: [15, 1, 55, 0, 0],
            4: [20, 1, 80, 0, 0], 5: [26, 1, 108, 0, 0], 6: [18, 2, 68, 0, 0],
            7: [20, 2, 78, 0, 0], 8: [24, 2, 97, 0, 0], 9: [30, 2, 116, 0, 0],
            10: [18, 2, 68, 2, 69]
        },
        M: {
            1: [10, 1, 16, 0, 0], 2: [16, 1, 28, 0, 0], 3: [26, 1, 44, 0, 0],
            4: [18, 2, 32, 0, 0], 5: [24, 2, 43, 0, 0], 6: [16, 4, 27, 0, 0],
            7: [18, 4, 31, 0, 0], 8: [22, 2, 38, 2, 39], 9: [22, 3, 36, 2, 37],
            10: [26, 4, 43, 1, 44]
        }
    };

    var ALIGN = {
        1: [], 2: [6, 18], 3: [6, 22], 4: [6, 26], 5: [6, 30],
        6: [6, 34], 7: [6, 22, 38], 8: [6, 24, 42], 9: [6, 26, 46],
        10: [6, 28, 50]
    };

    var MAX_VERSION = 10;

    var ECC_BITS = {
        L: 1,
        M: 0
    };

    function dataCodewords(version, ecl) {
        var spec = BLOCKS[ecl][version];

        return spec[1] * spec[2] + spec[3] * spec[4];
    }

    /* ---------------- bit buffer ---------------- */

    function BitBuffer() {
        this.bits = [];
    }

    BitBuffer.prototype.put = function (value, length) {
        for (var i = length - 1; i >= 0; i--) {
            this.bits.push((value >>> i) & 1);
        }
    };

    /* ---------------- payload -> codewords ---------------- */

    function utf8(text) {
        var out = [];
        var str = String(text);

        for (var i = 0; i < str.length; i++) {
            var code = str.charCodeAt(i);

            if (code < 0x80) {
                out.push(code);
            } else if (code < 0x800) {
                out.push(0xc0 | (code >> 6), 0x80 | (code & 0x3f));
            } else if (code >= 0xd800 && code <= 0xdbff && i + 1 < str.length) {
                var extra = str.charCodeAt(++i);
                var point = 0x10000 + ((code - 0xd800) << 10) + (extra - 0xdc00);
                out.push(
                    0xf0 | (point >> 18),
                    0x80 | ((point >> 12) & 0x3f),
                    0x80 | ((point >> 6) & 0x3f),
                    0x80 | (point & 0x3f)
                );
            } else {
                out.push(0xe0 | (code >> 12), 0x80 | ((code >> 6) & 0x3f), 0x80 | (code & 0x3f));
            }
        }

        return out;
    }

    function countBits(version) {
        return version <= 9 ? 8 : 16;
    }

    function pickVersion(byteCount, ecl) {
        for (var version = 1; version <= MAX_VERSION; version++) {
            var needed = 4 + countBits(version) + byteCount * 8;
            if (needed <= dataCodewords(version, ecl) * 8) {
                return version;
            }
        }

        return 0;
    }

    function buildCodewords(bytes, version, ecl) {
        var capacity = dataCodewords(version, ecl);
        var buffer = new BitBuffer();

        buffer.put(0x4, 4);                           // byte mode
        buffer.put(bytes.length, countBits(version));
        for (var i = 0; i < bytes.length; i++) {
            buffer.put(bytes[i], 8);
        }

        var maxBits = capacity * 8;
        var terminator = Math.min(4, maxBits - buffer.bits.length);
        buffer.put(0, terminator);

        while (buffer.bits.length % 8 !== 0) {
            buffer.bits.push(0);
        }

        var codewords = [];
        for (var b = 0; b < buffer.bits.length; b += 8) {
            var value = 0;
            for (var k = 0; k < 8; k++) {
                value = (value << 1) | buffer.bits[b + k];
            }
            codewords.push(value);
        }

        var pad = [0xec, 0x11];
        var padIndex = 0;
        while (codewords.length < capacity) {
            codewords.push(pad[padIndex % 2]);
            padIndex++;
        }

        return codewords;
    }

    function addEcc(codewords, version, ecl) {
        var spec = BLOCKS[ecl][version];
        var eccLen = spec[0];
        var counts = [spec[1], spec[2], spec[3], spec[4]];
        var divisor = rsDivisor(eccLen);
        var blocks = [];
        var offset = 0;
        var i;

        for (i = 0; i < counts[0]; i++) {
            var group1 = codewords.slice(offset, offset + counts[1]);
            offset += counts[1];
            blocks.push({ data: group1, ecc: rsRemainder(group1, divisor) });
        }

        for (i = 0; i < counts[2]; i++) {
            var group2 = codewords.slice(offset, offset + counts[3]);
            offset += counts[3];
            blocks.push({ data: group2, ecc: rsRemainder(group2, divisor) });
        }

        var result = [];
        var maxData = Math.max.apply(null, blocks.map(function (block) {
            return block.data.length;
        }));

        for (i = 0; i < maxData; i++) {
            for (var b = 0; b < blocks.length; b++) {
                if (i < blocks[b].data.length) {
                    result.push(blocks[b].data[i]);
                }
            }
        }

        for (i = 0; i < eccLen; i++) {
            for (var e = 0; e < blocks.length; e++) {
                result.push(blocks[e].ecc[i]);
            }
        }

        return result;
    }

    /* ---------------- matrix ---------------- */

    function Matrix(version) {
        this.version = version;
        this.size = version * 4 + 17;
        this.modules = [];
        this.reserved = [];
        for (var y = 0; y < this.size; y++) {
            var row = [];
            var flags = [];
            for (var x = 0; x < this.size; x++) {
                row.push(false);
                flags.push(false);
            }
            this.modules.push(row);
            this.reserved.push(flags);
        }
    }

    Matrix.prototype.setFunction = function (x, y, dark) {
        if (x < 0 || y < 0 || x >= this.size || y >= this.size) {
            return;
        }

        this.modules[y][x] = !!dark;
        this.reserved[y][x] = true;
    };

    Matrix.prototype.drawFunctionPatterns = function () {
        var size = this.size;
        var i;

        for (i = 0; i < size; i++) {
            this.setFunction(6, i, i % 2 === 0);
            this.setFunction(i, 6, i % 2 === 0);
        }

        this.drawFinder(3, 3);
        this.drawFinder(size - 4, 3);
        this.drawFinder(3, size - 4);

        var positions = ALIGN[this.version];
        for (i = 0; i < positions.length; i++) {
            for (var j = 0; j < positions.length; j++) {
                var px = positions[i];
                var py = positions[j];
                var corner = (px === 6 && py === 6)
                    || (px === 6 && py === size - 7)
                    || (px === size - 7 && py === 6);

                if (corner) {
                    continue;
                }

                for (var dy = -2; dy <= 2; dy++) {
                    for (var dx = -2; dx <= 2; dx++) {
                        this.setFunction(px + dx, py + dy, Math.max(Math.abs(dx), Math.abs(dy)) !== 1);
                    }
                }
            }
        }

        this.drawFormatBits(0);

        if (this.version >= 7) {
            var rem = this.version;
            for (i = 0; i < 12; i++) {
                rem = (rem << 1) ^ ((rem >>> 11) * 0x1f25);
            }
            var bits = (this.version << 12) | rem;
            for (i = 0; i < 18; i++) {
                var bit = (bits >>> i) & 1;
                var a = size - 11 + (i % 3);
                var b = Math.floor(i / 3);
                this.setFunction(a, b, bit);
                this.setFunction(b, a, bit);
            }
        }
    };

    Matrix.prototype.drawFinder = function (cx, cy) {
        for (var dy = -4; dy <= 4; dy++) {
            for (var dx = -4; dx <= 4; dx++) {
                var dist = Math.max(Math.abs(dx), Math.abs(dy));
                this.setFunction(cx + dx, cy + dy, dist !== 2 && dist !== 4);
            }
        }
    };

    Matrix.prototype.drawFormatBits = function (mask) {
        var data = (ECC_BITS[this.ecl] << 3) | mask;
        var rem = data;

        for (var i = 0; i < 10; i++) {
            rem = (rem << 1) ^ ((rem >>> 9) * 0x537);
        }

        var bits = ((data << 10) | rem) ^ 0x5412;
        var size = this.size;

        for (i = 0; i <= 5; i++) {
            this.setFunction(8, i, (bits >>> i) & 1);
        }
        this.setFunction(8, 7, (bits >>> 6) & 1);
        this.setFunction(8, 8, (bits >>> 7) & 1);
        this.setFunction(7, 8, (bits >>> 8) & 1);

        for (i = 9; i < 15; i++) {
            this.setFunction(14 - i, 8, (bits >>> i) & 1);
        }

        for (i = 0; i < 8; i++) {
            this.setFunction(size - 1 - i, 8, (bits >>> i) & 1);
        }
        for (i = 8; i < 15; i++) {
            this.setFunction(8, size - 15 + i, (bits >>> i) & 1);
        }

        this.setFunction(8, size - 8, true);
    };

    Matrix.prototype.drawCodewords = function (codewords) {
        var size = this.size;
        var bitIndex = 0;
        var totalBits = codewords.length * 8;

        for (var right = size - 1; right >= 1; right -= 2) {
            if (right === 6) {
                right = 5;
            }

            for (var vert = 0; vert < size; vert++) {
                for (var j = 0; j < 2; j++) {
                    var x = right - j;
                    var upward = ((right + 1) & 2) === 0;
                    var y = upward ? size - 1 - vert : vert;

                    if (!this.reserved[y][x] && bitIndex < totalBits) {
                        this.modules[y][x] = ((codewords[bitIndex >>> 3] >>> (7 - (bitIndex & 7))) & 1) !== 0;
                        bitIndex++;
                    }
                }
            }
        }
    };

    Matrix.prototype.applyMask = function (mask) {
        for (var y = 0; y < this.size; y++) {
            for (var x = 0; x < this.size; x++) {
                if (this.reserved[y][x]) {
                    continue;
                }

                var product = x * y;
                var invert;

                switch (mask) {
                    case 0: invert = (x + y) % 2 === 0; break;
                    case 1: invert = y % 2 === 0; break;
                    case 2: invert = x % 3 === 0; break;
                    case 3: invert = (x + y) % 3 === 0; break;
                    case 4: invert = (Math.floor(x / 3) + Math.floor(y / 2)) % 2 === 0; break;
                    case 5: invert = (product % 2) + (product % 3) === 0; break;
                    case 6: invert = ((product % 2) + (product % 3)) % 2 === 0; break;
                    default: invert = ((x + y) % 2 + (product % 3)) % 2 === 0; break;
                }

                if (invert) {
                    this.modules[y][x] = !this.modules[y][x];
                }
            }
        }
    };

    Matrix.prototype.penalty = function () {
        var size = this.size;
        var score = 0;
        var x, y, i;

        for (y = 0; y < size; y++) {
            var runColor = this.modules[y][0];
            var runLength = 1;
            for (x = 1; x < size; x++) {
                if (this.modules[y][x] === runColor) {
                    runLength++;
                    if (runLength === 5) {
                        score += 3;
                    } else if (runLength > 5) {
                        score++;
                    }
                } else {
                    runColor = this.modules[y][x];
                    runLength = 1;
                }
            }
        }

        for (x = 0; x < size; x++) {
            var colColor = this.modules[0][x];
            var colLength = 1;
            for (y = 1; y < size; y++) {
                if (this.modules[y][x] === colColor) {
                    colLength++;
                    if (colLength === 5) {
                        score += 3;
                    } else if (colLength > 5) {
                        score++;
                    }
                } else {
                    colColor = this.modules[y][x];
                    colLength = 1;
                }
            }
        }

        for (y = 0; y < size - 1; y++) {
            for (x = 0; x < size - 1; x++) {
                var c = this.modules[y][x];
                if (c === this.modules[y][x + 1] && c === this.modules[y + 1][x] && c === this.modules[y + 1][x + 1]) {
                    score += 3;
                }
            }
        }

        var patterns = ['10111010000', '00001011101'];
        for (y = 0; y < size; y++) {
            var rowString = '';
            for (x = 0; x < size; x++) {
                rowString += this.modules[y][x] ? '1' : '0';
            }
            for (i = 0; i < patterns.length; i++) {
                var at = rowString.indexOf(patterns[i]);
                while (at !== -1) {
                    score += 40;
                    at = rowString.indexOf(patterns[i], at + 1);
                }
            }
        }

        for (x = 0; x < size; x++) {
            var colString = '';
            for (y = 0; y < size; y++) {
                colString += this.modules[y][x] ? '1' : '0';
            }
            for (i = 0; i < patterns.length; i++) {
                var colAt = colString.indexOf(patterns[i]);
                while (colAt !== -1) {
                    score += 40;
                    colAt = colString.indexOf(patterns[i], colAt + 1);
                }
            }
        }

        var dark = 0;
        for (y = 0; y < size; y++) {
            for (x = 0; x < size; x++) {
                if (this.modules[y][x]) {
                    dark++;
                }
            }
        }

        var total = size * size;
        var k = Math.ceil(Math.abs(dark * 20 - total * 10) / total) - 1;
        score += Math.max(0, k) * 10;

        return score;
    };

    /* ---------------- public API ---------------- */

    function matrix(text, preferredEcl) {
        var bytes = utf8(text);
        var order = preferredEcl === 'L' ? ['L', 'M'] : ['M', 'L'];
        var version = 0;
        var ecl = order[0];

        for (var i = 0; i < order.length; i++) {
            version = pickVersion(bytes.length, order[i]);
            if (version) {
                ecl = order[i];
                break;
            }
        }

        if (!version) {
            throw new Error('ShipQR: payload too long for the built-in versions (max ~270 bytes)');
        }

        var codewords = addEcc(buildCodewords(bytes, version, ecl), version, ecl);
        var best = null;
        var bestScore = Infinity;

        for (var mask = 0; mask < 8; mask++) {
            var candidate = new Matrix(version);
            candidate.ecl = ecl;
            candidate.drawFunctionPatterns();
            candidate.drawCodewords(codewords);
            candidate.applyMask(mask);
            candidate.drawFormatBits(mask);

            var score = candidate.penalty();
            if (score < bestScore) {
                bestScore = score;
                best = candidate;
            }
        }

        var rows = [];
        for (var y = 0; y < best.size; y++) {
            rows.push(best.modules[y].map(function (on) {
                return on ? 1 : 0;
            }));
        }

        return { size: best.size, version: version, ecl: ecl, modules: rows };
    }

    function svg(text, options) {
        options = options || {};

        var data = matrix(text, options.ecl);
        var quiet = options.quiet === undefined ? 2 : options.quiet;
        var dimension = data.size + quiet * 2;
        var path = '';

        for (var y = 0; y < data.size; y++) {
            for (var x = 0; x < data.size; x++) {
                if (data.modules[y][x]) {
                    path += 'M' + (x + quiet) + ' ' + (y + quiet) + 'h1v1h-1z';
                }
            }
        }

        var dark = options.dark || '#101828';
        var light = options.light || '#ffffff';

        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' + dimension + ' ' + dimension
            + '" role="img" aria-label="' + escapeAttribute(options.alt || text) + '"'
            + ' shape-rendering="crispEdges" focusable="false">'
            + '<rect width="' + dimension + '" height="' + dimension + '" fill="' + light + '"/>'
            + '<path d="' + path + '" fill="' + dark + '"/>'
            + '</svg>';
    }

    function escapeAttribute(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function render(root) {
        var scope = root || document;
        var targets = scope.querySelectorAll('[data-ship-qr]');

        Array.prototype.forEach.call(targets, function (node) {
            var value = node.getAttribute('data-ship-qr');
            if (!value) {
                return;
            }

            try {
                var markup = svg(value, {
                    ecl: node.getAttribute('data-ship-qr-ecl') || 'M',
                    quiet: node.hasAttribute('data-ship-qr-quiet')
                        ? parseInt(node.getAttribute('data-ship-qr-quiet'), 10)
                        : 2,
                    dark: node.getAttribute('data-ship-qr-dark') || undefined,
                    light: node.getAttribute('data-ship-qr-light') || undefined,
                    alt: node.getAttribute('data-ship-qr-caption') || value
                });

                node.innerHTML = markup;
                node.classList.add('ship-qr-ready');
            } catch (error) {
                node.classList.add('ship-qr-failed');
                node.setAttribute('data-ship-qr-error', error.message);
            }
        });
    }

    var api = {
        matrix: matrix,
        svg: svg,
        render: render,
        rsDivisor: rsDivisor,
        version: '1.0.0'
    };

    global.ShipQR = api;

    if (typeof document !== 'undefined') {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', function () {
                render(document);
            });
        } else {
            render(document);
        }
    }
})(typeof window !== 'undefined' ? window : this);

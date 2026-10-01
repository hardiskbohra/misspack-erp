<label</label><label</label><label</label><label</label><label</label><label</label><label</label><label</label><label</label><label</label><label</label><label</label><label</label><label</label><label</label><label</label><label</label><label</label><label</label><label</label><label</label><label</label><input class="master-input"
                            name="billing_pincode" value="{{ old('billing_pincode', $client->billing_pincode) }}"
                            required @readonly($readonly)></div>
                    <label class="master-check"><input type="checkbox" name="shipping_same_as_billing" value="1"
                            @checked(old('shipping_same_as_billing', $client-></label><label</label><label</label><label</label><label</label><label</label><label</label><label</label><label</label><label</label><label</label><label</label><label</label><label</label><label</label><label</label><label</label><label</label><label</label><label</label><label</label><input class="master-input" name="notes"-->
            <!--                @readonly($readonly) value="{{ old('notes', $client->notes) }}"></div>-->
            <!--    </div>-->
            <!--</div>-->

            @unless($readonly)
                <div class="actions" style="justify-content: center;">
                    <button type="submit" class="master-btn master-btn-primary px-4">
                        Submit KYC for Review
                    </button>
                </div>
            @endunless
        </form>
        
        <footer class="card kyc-footer">
        <div class="kyc-footer-top" style="background:#D9A6A2;">
            <div class="kyc-footer-brand">
                <img src="{{ asset('images/logo-dark.png') }}" alt="MissPack - Packed Perfect">
                <p>MissPack helps brands source, develop and manage packaging with reliable client coordination and transparent documentation.</p>
                <div class="kyc-social" aria-label="Social media links">
                    <a href="https://www.facebook.com/misspackindia" target="_blank" rel="noopener" title="Facebook" aria-label="Facebook">
                        <i class="fa-brands fa-facebook"></i>
                    </a>
                
                    <a href="https://www.instagram.com/themisspack" target="_blank" rel="noopener" title="Instagram" aria-label="Instagram">
                        <i class="fa-brands fa-instagram"></i>
                    </a>
                
                    <a href="https://www.linkedin.com/company/misspackindia/" target="_blank" rel="noopener" title="LinkedIn" aria-label="LinkedIn">
                        <i class="fa-brands fa-linkedin-in"></i>
                    </a>
                
                    <a href="https://wa.me/917048110823" target="_blank" rel="noopener" title="WhatsApp" aria-label="WhatsApp">
                        <i class="fa-brands fa-whatsapp"></i>
                    </a>
                </div>
            </div>

            <div class="kyc-footer-col">
                <h4>Contact Details</h4>
                <div class="kyc-footer-list">
                    <a href="mailto:misspackindia@gmail.com"><i class="fa-regular fa-envelope"></i> misspackindia@gmail.com</a>
                    <a href="tel:+917048110823">☎ +91 70481 10823</a>
                    <a href="https://www.themisspack.com" target="_blank" rel="noopener">🌐 www.themisspack.com</a>
                    <span>🕘 Monday to Saturday, 10:00 AM - 7:00 PM</span>
                </div>
            </div>

            <div class="kyc-footer-col">
                <h4>Address</h4>
                <p><span><b>MissPack India Private Limited</b></span><br>Ahmedabad, Gujarat, India</p>
                <p style="margin-top:10px;">For KYC support, please contact our accounts or sales coordination team.</p>
            </div>
        </div>
        <div class="kyc-footer-bottom" style="background:#2f3a4c;color:grey;">
            <span>© {{ date('Y') }} MissPack. All rights reserved.</span>
            <span>Powered by <a href="https://themisspack.com" target="_blank" rel="noopener">MissPack</a> · Packed Perfect</span>
        </div>
    </footer>
    </div>
</body>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
    window.kycToast = @json($kycToast);
</script>
<script src="{{ asset('assets/js/kyc-public.js') }}"></script>

</html>
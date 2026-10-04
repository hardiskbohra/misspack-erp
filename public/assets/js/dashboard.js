/* ==========================================================================
   DASHBOARD.JS — Dashboard module (index view)
   --------------------------------------------------------------------------
   Canvas chart engine for the admin dashboard:
     - line / bar / donut / horizontal-bar charts drawn on <canvas>
     - shared tooltip with per-chart hit regions (points, bars, donut arcs)
     - tab panel switching re-renders charts; window resize redraws
     - chart series data arrives via the #dashboardRoot data-chart-data
       bridge (Blade cannot render inside an external script)
   ========================================================================== */
(function () {
    'use strict';

    function onReady(fn) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', fn);
        } else {
            fn();
        }
    }

    onReady(function () {
        const dashData = JSON.parse(document.getElementById('dashboardRoot').getAttribute('data-chart-data') || '{}');
        const colors = ['#4f83f1','#10b981','#ef4770','#f59e0b','#8b5cf6','#12cbb7','#17233b','#e11d48','#06b6d4','#84cc16'];
        const chartStore = {};
        const tooltip = document.getElementById('dashTooltip');

        function formatMoney(v){v=Number(v)||0;return '₹'+v.toLocaleString('en-IN',{maximumFractionDigits:2});}
        function formatShort(v){v=Number(v)||0;if(Math.abs(v)>=10000000)return '₹'+(v/10000000).toFixed(2)+' Cr';if(Math.abs(v)>=100000)return '₹'+(v/100000).toFixed(2)+' L';if(Math.abs(v)>=1000)return '₹'+(v/1000).toFixed(1)+' K';return '₹'+v.toFixed(0);}
        function setupCanvas(canvas){const dpr=window.devicePixelRatio||1;const rect=canvas.getBoundingClientRect();const h=parseInt(canvas.getAttribute('height'))||260;canvas.width=Math.max(rect.width,320)*dpr;canvas.height=h*dpr;const ctx=canvas.getContext('2d');ctx.setTransform(dpr,0,0,dpr,0,0);return {ctx,width:Math.max(rect.width,320),height:h};}
        function showTip(e, hit){if(!hit){tooltip.style.display='none';return;}tooltip.innerHTML='<strong>'+hit.title+'</strong><span>'+hit.label+'</span><span>'+hit.value+'</span>';tooltip.style.display='block';tooltip.style.left=(e.clientX+14)+'px';tooltip.style.top=(e.clientY+14)+'px';}
        function bindHover(canvas,id){canvas.onmousemove=function(e){const rect=canvas.getBoundingClientRect();const x=e.clientX-rect.left,y=e.clientY-rect.top;const store=chartStore[id]||{hits:[]};let hit=null;if(store.type==='donut'){for(const h of store.hits){const dx=x-h.cx,dy=y-h.cy,dist=Math.sqrt(dx*dx+dy*dy);let angle=Math.atan2(dy,dx);if(angle<-Math.PI/2)angle+=Math.PI*2;if(dist>=h.inner&&dist<=h.r&&angle>=h.start&&angle<=h.end){hit=h;break;}}}else{for(const h of store.hits){if(h.kind==='rect'&&x>=h.x&&x<=h.x+h.w&&y>=h.y&&y<=h.y+h.h){hit=h;break;}if(h.kind==='point'){const dx=x-h.x,dy=y-h.y;if(Math.sqrt(dx*dx+dy*dy)<=8){hit=h;break;}}}}showTip(e,hit);};canvas.onmouseleave=function(){tooltip.style.display='none';};}
        function niceMax(values){const max=Math.max(...values.map(v=>Number(v)||0),1);return max*1.15;}
        function drawLineChart(id,labels,datasets,prefix='₹'){const canvas=document.getElementById(id);if(!canvas)return;const {ctx,width,height}=setupCanvas(canvas);const pad={l:54,r:18,t:24,b:38};const plotW=width-pad.l-pad.r,plotH=height-pad.t-pad.b;const values=[];datasets.forEach(ds=>ds.data.forEach(v=>values.push(Number(v)||0)));const max=niceMax(values),min=Math.min(0,...values);const hits=[];ctx.clearRect(0,0,width,height);ctx.strokeStyle='#dfe7f3';ctx.fillStyle='#687386';ctx.font='11px Inter,Arial';for(let i=0;i<=4;i++){let y=pad.t+(plotH/4)*i;ctx.beginPath();ctx.moveTo(pad.l,y);ctx.lineTo(width-pad.r,y);ctx.stroke();ctx.fillText(formatShort(max-((max-min)/4)*i),6,y+4);}datasets.forEach((ds,di)=>{ctx.strokeStyle=ds.color||colors[di];ctx.lineWidth=2.5;ctx.beginPath();ds.data.forEach((v,i)=>{let x=pad.l+(labels.length<=1?0:(plotW/(labels.length-1))*i);let y=pad.t+plotH-(((Number(v)||0)-min)/(max-min||1))*plotH;if(i===0)ctx.moveTo(x,y);else ctx.lineTo(x,y);});ctx.stroke();ds.data.forEach((v,i)=>{let x=pad.l+(labels.length<=1?0:(plotW/(labels.length-1))*i);let y=pad.t+plotH-(((Number(v)||0)-min)/(max-min||1))*plotH;ctx.fillStyle=ds.color||colors[di];ctx.beginPath();ctx.arc(x,y,3.5,0,Math.PI*2);ctx.fill();hits.push({kind:'point',x,y,title:ds.label,label:labels[i],value:prefix?formatMoney(v):v});});});const step=Math.max(1,Math.ceil(labels.length/6));ctx.fillStyle='#687386';labels.forEach((lab,i)=>{if(i%step===0||i===labels.length-1){let x=pad.l+(labels.length<=1?0:(plotW/(labels.length-1))*i);ctx.fillText(lab,x-14,height-12);}});drawLegend(ctx,datasets,pad.l,8);chartStore[id]={type:'line',hits};bindHover(canvas,id);}
        function drawBarChart(id,labels,datasets,prefix='₹'){const canvas=document.getElementById(id);if(!canvas)return;const {ctx,width,height}=setupCanvas(canvas);const pad={l:54,r:16,t:24,b:40};const plotW=width-pad.l-pad.r,plotH=height-pad.t-pad.b;const values=[];datasets.forEach(ds=>ds.data.forEach(v=>values.push(Number(v)||0)));const max=niceMax(values);const hits=[];ctx.clearRect(0,0,width,height);ctx.strokeStyle='#dfe7f3';ctx.fillStyle='#687386';ctx.font='11px Inter,Arial';for(let i=0;i<=4;i++){let y=pad.t+(plotH/4)*i;ctx.beginPath();ctx.moveTo(pad.l,y);ctx.lineTo(width-pad.r,y);ctx.stroke();ctx.fillText(formatShort(max-(max/4)*i),6,y+4);}const groupW=plotW/Math.max(labels.length,1);const barW=Math.max(5,(groupW-10)/datasets.length);labels.forEach((lab,i)=>{datasets.forEach((ds,di)=>{let v=Number(ds.data[i])||0;let h=(v/max)*plotH;let x=pad.l+i*groupW+5+di*barW;let y=pad.t+plotH-h;ctx.fillStyle=ds.color||colors[di];roundRect(ctx,x,y,barW-2,h,5);ctx.fill();hits.push({kind:'rect',x,y,w:barW-2,h,title:ds.label,label:lab,value:prefix?formatMoney(v):v});});});const step=Math.max(1,Math.ceil(labels.length/6));ctx.fillStyle='#687386';labels.forEach((lab,i)=>{if(i%step===0||i===labels.length-1)ctx.fillText(lab,pad.l+i*groupW,height-12);});drawLegend(ctx,datasets,pad.l,8);chartStore[id]={type:'bar',hits};bindHover(canvas,id);}
        function drawHorizontalBar(id,dataObj,prefix='₹'){const canvas=document.getElementById(id);if(!canvas)return;const entries=Object.entries(dataObj||{}).filter(e=>Number(e[1])>0).slice(0,8);const {ctx,width,height}=setupCanvas(canvas);const pad={l:130,r:20,t:20,b:20};const plotW=width-pad.l-pad.r;const rowH=(height-pad.t-pad.b)/Math.max(entries.length,1);const max=niceMax(entries.map(e=>e[1]));const hits=[];ctx.clearRect(0,0,width,height);ctx.font='12px Inter,Arial';if(!entries.length){ctx.fillStyle='#687386';ctx.textAlign='center';ctx.fillText('No data',width/2,height/2);chartStore[id]={type:'bar',hits};bindHover(canvas,id);return;}entries.forEach((e,i)=>{let label=e[0],v=Number(e[1])||0,y=pad.t+i*rowH+6,h=Math.max(12,rowH-12),w=(v/max)*plotW;ctx.fillStyle='#687386';ctx.textAlign='right';ctx.fillText(label.substring(0,18),pad.l-8,y+h/2+4);ctx.fillStyle=colors[i%colors.length];roundRect(ctx,pad.l,y,w,h,7);ctx.fill();ctx.fillStyle='#17233b';ctx.textAlign='left';ctx.fillText(formatShort(v),pad.l+w+6,y+h/2+4);hits.push({kind:'rect',x:pad.l,y,w,h,title:label,label:'Value',value:prefix?formatMoney(v):v});});chartStore[id]={type:'bar',hits};bindHover(canvas,id);}
        function drawDonut(id,dataObj,legendId,prefix=''){const canvas=document.getElementById(id);if(!canvas)return;const {ctx,width,height}=setupCanvas(canvas);const entries=Object.entries(dataObj||{}).filter(e=>Number(e[1])>0);const total=entries.reduce((s,e)=>s+Number(e[1]),0);const cx=width/2,cy=height/2,r=Math.min(width,height)/2-18,inner=r*.58;const hits=[];ctx.clearRect(0,0,width,height);if(!total){ctx.fillStyle='#687386';ctx.font='13px Inter,Arial';ctx.textAlign='center';ctx.fillText('No data',cx,cy);return;}let start=-Math.PI/2;entries.forEach((e,i)=>{let val=Number(e[1]);let end=start+(val/total)*Math.PI*2;ctx.beginPath();ctx.arc(cx,cy,r,start,end);ctx.arc(cx,cy,inner,end,start,true);ctx.closePath();ctx.fillStyle=colors[i%colors.length];ctx.fill();hits.push({cx,cy,r,inner,start,end,title:e[0],label:((val/total)*100).toFixed(1)+'%',value:prefix?formatMoney(val):val});start=end;});ctx.fillStyle='#17233b';ctx.font='900 24px Inter,Arial';ctx.textAlign='center';ctx.fillText(prefix?formatShort(total):total,cx,cy+5);ctx.font='11px Inter,Arial';ctx.fillStyle='#687386';ctx.fillText('Total',cx,cy+23);const legend=document.getElementById(legendId);if(legend){legend.innerHTML=entries.map((e,i)=>`<span class="master-legend-item"><i class="master-legend-color" style="background:${colors[i%colors.length]}"></i>${e[0]} (${prefix?formatShort(e[1]):e[1]})</span>`).join('');}chartStore[id]={type:'donut',hits};bindHover(canvas,id);}
        function drawLegend(ctx,datasets,x,y){ctx.font='11px Inter,Arial';let lx=x;datasets.forEach((ds,di)=>{ctx.fillStyle=ds.color||colors[di];ctx.fillRect(lx,y,10,10);ctx.fillStyle='#17233b';ctx.fillText(ds.label,lx+14,y+9);lx+=ctx.measureText(ds.label).width+42;});}
        function roundRect(ctx,x,y,w,h,r){ctx.beginPath();ctx.moveTo(x+r,y);ctx.lineTo(x+w-r,y);ctx.quadraticCurveTo(x+w,y,x+w,y+r);ctx.lineTo(x+w,y+h-r);ctx.quadraticCurveTo(x+w,y+h,x+w-r,y+h);ctx.lineTo(x+r,y+h);ctx.quadraticCurveTo(x,y+h,x,y+h-r);ctx.lineTo(x,y+r);ctx.quadraticCurveTo(x,y,x+r,y);ctx.closePath();}
        function drawAllCharts(){
            const labels=dashData.labels;
            drawLineChart('overviewComboChart',labels,[{label:'Sales',data:dashData.salesPurchase.sales,color:'#4f83f1'},{label:'Purchase',data:dashData.salesPurchase.purchase,color:'#f59e0b'},{label:'Income',data:dashData.incomeExpense.income,color:'#10b981'},{label:'Expenses',data:dashData.incomeExpense.expense,color:'#ef4770'}]);
            drawDonut('projectHealthChart',dashData.status.projectHealth,'projectHealthLegend','');
            drawDonut('shipmentStatusChart',dashData.status.shipments,'shipmentStatusLegend','');
            drawDonut('expenseCategoryChart',dashData.pies.expenseCategories,'expenseCategoryLegend','₹');
            drawBarChart('pipelineChart',labels,[{label:'Leads',data:dashData.pipeline.leads,color:'#8b5cf6'},{label:'Quotes',data:dashData.pipeline.quotes,color:'#4f83f1'},{label:'Projects',data:dashData.pipeline.projects,color:'#ef4770'},{label:'Shipments',data:dashData.pipeline.shipments,color:'#10b981'}],'');
            drawBarChart('salesPurchaseChart',labels,[{label:'Sales',data:dashData.salesPurchase.sales,color:'#4f83f1'},{label:'Purchase',data:dashData.salesPurchase.purchase,color:'#f59e0b'}]);
            drawLineChart('grossMarginChart',labels,[{label:'Gross Margin',data:dashData.salesPurchase.margin,color:'#10b981'}]);
            drawHorizontalBar('salesByClientChart',dashData.pies.salesByClient,'₹');
            drawHorizontalBar('salesByProductChart',dashData.pies.salesByProduct,'₹');
            drawHorizontalBar('purchaseByVendorChart',dashData.pies.purchaseByVendor,'₹');
            drawBarChart('incomeExpenseChart',labels,[{label:'Income',data:dashData.incomeExpense.income,color:'#10b981'},{label:'Expenses',data:dashData.incomeExpense.expense,color:'#ef4770'}]);
            drawLineChart('netIncomeChart',labels,[{label:'Net Income',data:dashData.incomeExpense.net,color:'#4f83f1'}]);
            drawDonut('expenseCategoryChart2',dashData.pies.expenseCategories,'expenseCategoryLegend2','₹');
            drawBarChart('projectPaymentChart',labels,[{label:'Project Inward',data:dashData.projectPayments.inward,color:'#4f83f1'},{label:'Project Outward',data:dashData.projectPayments.outward,color:'#ef4770'}]);
        }
        function togglePeriodControls(){const type=document.getElementById('periodType').value;document.querySelectorAll('.period-control').forEach(el=>el.style.display='none');document.querySelectorAll('.period-'+type).forEach(el=>el.style.display='flex');}
        togglePeriodControls();document.getElementById('periodType')?.addEventListener('change',togglePeriodControls);
        document.querySelectorAll('.master-tab').forEach(btn=>btn.addEventListener('click',function(){document.querySelectorAll('.master-tab').forEach(b=>b.classList.remove('active'));btn.classList.add('active');document.querySelectorAll('.master-panel').forEach(p=>p.classList.toggle('active',p.dataset.panel===btn.dataset.tab));setTimeout(drawAllCharts,60);}));
        drawAllCharts();
        window.addEventListener('resize',function(){clearTimeout(window.dashResizeTimer);window.dashResizeTimer=setTimeout(drawAllCharts,180);});
    });
})();

/* Race tools on the wind map (racers only): Polars & Target Speed overlay,
   GPS Race Log, shared team archive + replay. Server side: inc/race-tools.php.
   Reads the live boat + model wind through window.OYCWM (inc/wind-map.php). */
(function(){
"use strict";
var CFG=window.OYC_RT;if(!CFG)return;

/* ---------- small helpers ---------- */
function $(id){return document.getElementById(id);}
function esc(s){return String(s==null?'':s).replace(/[&<>"']/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];});}
function ls(k,v){try{if(v===undefined){var r=localStorage.getItem(k);return r==null?null:JSON.parse(r);}if(v===null)localStorage.removeItem(k);else localStorage.setItem(k,JSON.stringify(v));}catch(e){return null;}}
function angDiff(a,b){return ((a-b+540)%360)-180;}               /* signed a-b, -180..180 */
function twaOf(cog,twd){return Math.abs(angDiff(cog,twd));}      /* 0..180 */
function distM(a,b,c,d){var R=6371000,r=Math.PI/180,x=(d-b)*r*Math.cos((a+c)/2*r),y=(c-a)*r;return Math.sqrt(x*x+y*y)*R;}
function brg(a,b,c,d){var r=Math.PI/180,p1=a*r,p2=c*r,dl=(d-b)*r;return (Math.atan2(Math.sin(dl)*Math.cos(p2),Math.cos(p1)*Math.sin(p2)-Math.sin(p1)*Math.cos(p2)*Math.cos(dl))/r+360)%360;}
function pad(n){return (n<10?'0':'')+n;}
function hms(s){s=Math.max(0,Math.round(s));var h=Math.floor(s/3600),m=Math.floor(s%3600/60),x=s%60;return (h?h+':'+pad(m):m)+':'+pad(x);}
function clock(ms){var d=new Date(ms);return (d.getHours()%12||12)+':'+pad(d.getMinutes())+':'+pad(d.getSeconds())+(d.getHours()>=12?' pm':' am');}
function fmtDate(ms){return new Date(ms).toLocaleDateString('en-US',{weekday:'short',month:'short',day:'numeric',year:'numeric'});}
function ajax(action,data,method){
	var url=CFG.ajax+'?action='+encodeURIComponent(action),opt={credentials:'same-origin'};
	data=data||{};data.nonce=CFG.nonce;
	if(method==='GET'){Object.keys(data).forEach(function(k){url+='&'+encodeURIComponent(k)+'='+encodeURIComponent(data[k]);});}
	else{var fd=new FormData();Object.keys(data).forEach(function(k){fd.append(k,data[k]);});opt.method='POST';opt.body=fd;}
	return fetch(url,opt).then(function(r){return r.json().catch(function(){throw new Error('The server did not answer (status '+r.status+').');});})
		.then(function(j){if(!j||!j.success)throw new Error((j&&j.data&&j.data.msg)||'Something went wrong.');return j.data;});
}
function msg(el,t,cls){if(!el)return;el.textContent=t||'';el.className='rt-msg'+(cls?' '+cls:'');}

/* ---------- polars ---------- */
/* Parse a polar file. Two layouts:
   grid  — header row of TWS (first cell a label like "twa/tws" or 0), then rows: TWA, BSP@each TWS
   pairs — Expedition style rows: TWS, TWA1, BSP1, TWA2, BSP2, …
   Returns {tws:[],twa:[],bsp:[[..]..]} (rows = twa) or throws. */
function parsePolar(text){
	var lines=String(text||'').replace(/\r/g,'').split('\n').map(function(l){return l.trim();})
		.filter(function(l){return l&&!/^(#|!|\/\/)/.test(l);});
	if(lines.length<2)throw new Error('That doesn’t look like a polar table.');
	function toks(l){return l.split(/[\s,;\t]+/).filter(function(x){return x!=='';});}
	function num(x){var v=parseFloat(String(x).replace(/[^\d.\-]/g,''));return isFinite(v)?v:NaN;}
	var first=toks(lines[0]),head=null,rows=[],i;
	if(isNaN(num(first[0]))||/[a-z]/i.test(first[0])){head=first.slice(1).map(num).filter(function(v){return !isNaN(v);});i=1;}
	else i=0;
	for(;i<lines.length;i++){var t=toks(lines[i]).map(num);if(t.length<2||isNaN(t[0]))continue;rows.push(t.map(function(v){return isNaN(v)?0:v;}));}
	if(!head&&rows.length>2){ /* all-numeric header? first row starts 0 and the rest ascend */
		var r0=rows[0],asc=r0.length>2&&r0[0]===0;for(var k=2;asc&&k<r0.length;k++)if(!(r0[k]>r0[k-1]))asc=false;
		var same=rows.slice(1).every(function(r){return r.length===r0.length;});
		if(asc&&same){head=r0.slice(1);rows=rows.slice(1);}
	}
	var P;
	if(head&&head.length>=2){
		P={tws:head,twa:[],bsp:[]};
		rows.forEach(function(r){if(r[0]<0||r[0]>180)return;var b=[];for(var j=0;j<head.length;j++)b.push(r[j+1]!=null?r[j+1]:0);P.twa.push(r[0]);P.bsp.push(b);});
	}else{
		/* pairs layout */
		var tws=[],curves=[],angs={};
		rows.forEach(function(r){if(r.length<5)return;var c=[];for(var j=1;j+1<r.length;j+=2){if(r[j]>0&&r[j]<=180){c.push([r[j],r[j+1]]);angs[r[j]]=1;}}
			if(c.length>=2){c.sort(function(a,b){return a[0]-b[0];});tws.push(r[0]);curves.push(c);}});
		if(tws.length<2)throw new Error('Couldn’t find a wind-speed × wind-angle grid in that file.');
		var twa=Object.keys(angs).map(Number).sort(function(a,b){return a-b;});
		P={tws:tws,twa:twa,bsp:twa.map(function(a){return curves.map(function(c){return interp1(c,a);});})};
	}
	/* sort both axes ascending */
	var oi=P.tws.map(function(v,i){return i;}).sort(function(a,b){return P.tws[a]-P.tws[b];});
	var ri=P.twa.map(function(v,i){return i;}).sort(function(a,b){return P.twa[a]-P.twa[b];});
	P={tws:oi.map(function(i){return P.tws[i];}),twa:ri.map(function(i){return P.twa[i];}),bsp:ri.map(function(r){return oi.map(function(c){return P.bsp[r][c];});})};
	/* drop an all-zero TWA 0 row (some files carry one) — it would drag beats down */
	while(P.twa.length&&P.twa[0]<20&&P.bsp[0].every(function(v){return !v;})){P.twa.shift();P.bsp.shift();}
	if(P.tws.length<2||P.twa.length<2)throw new Error('The table needs at least two wind speeds and two wind angles.');
	if(P.tws.length>80||P.twa.length>80)throw new Error('That table is bigger than expected (max 80 × 80).');
	var any=P.bsp.some(function(r){return r.some(function(v){return v>0;});});
	if(!any)throw new Error('All boat speeds in that table are zero.');
	return P;
}
function interp1(c,x){ /* c = [[x,y]..] sorted */
	if(x<=c[0][0])return c[0][1];if(x>=c[c.length-1][0])return c[c.length-1][1];
	for(var i=1;i<c.length;i++)if(x<=c[i][0]){var f=(x-c[i-1][0])/(c[i][0]-c[i-1][0]);return c[i-1][1]+(c[i][1]-c[i-1][1])*f;}
	return c[c.length-1][1];
}
function idx(arr,v){ /* bracket index + fraction, clamped */
	if(v<=arr[0])return [0,0,0];var n=arr.length-1;if(v>=arr[n])return [n,n,0];
	for(var i=1;i<=n;i++)if(v<=arr[i])return [i-1,i,(v-arr[i-1])/(arr[i]-arr[i-1])];return [n,n,0];
}
/* theoretical boat speed for this wind speed + angle (bilinear; below the
   lightest wind in the table it scales down to 0 at 0 kt) */
function polarSpeed(P,tws,twa){
	if(!P||tws==null||twa==null)return null;
	var scale=1;if(tws<P.tws[0]){scale=P.tws[0]>0?Math.max(0,tws/P.tws[0]):0;tws=P.tws[0];}
	var a=idx(P.twa,twa),w=idx(P.tws,tws),b=P.bsp;
	function g(r,c){return b[r][c]||0;}
	var top=g(a[0],w[0])+(g(a[0],w[1])-g(a[0],w[0]))*w[2],bot=g(a[1],w[0])+(g(a[1],w[1])-g(a[1],w[0]))*w[2];
	return (top+(bot-top)*a[2])*scale;
}
/* best VMG angle + speed upwind (up=true) or downwind for a wind speed */
function vmgTarget(P,tws,up){
	var best=null,lo=Math.max(up?28:95,P.twa[0]),hi=Math.min(up?85:180,P.twa[P.twa.length-1]);for(var a=lo;a<=hi;a+=1){var s=polarSpeed(P,tws,a),v=s*Math.cos(a*Math.PI/180)*(up?1:-1);if(!best||v>best.vmg)best={twa:a,bsp:s,vmg:v};}
	return best;
}
function band(p){if(p==null)return '';if(p<90)return 'b-low';if(p<100)return 'b-mid';if(p<=105)return 'b-ok';return 'b-hi';}
function bandText(p){if(p==null)return '';if(p<90)return 'Below 90%';if(p<100)return '90–100%';if(p<=105)return 'On target';return 'Above 105%';}
function pctColor(p){if(p==null)return '#8a99a8';if(p<85)return '#8f2d20';if(p<90)return '#cf5638';if(p<95)return '#e0a13f';if(p<100)return '#d9c07a';if(p<=105)return '#2e9e5b';return '#1960aa';}
function spdColor(kt,lo,hi){var f=hi>lo?(kt-lo)/(hi-lo):0.5;f=Math.max(0,Math.min(1,f));var C=['#3b4cc0','#5aa6d0','#93c47d','#e0a13f','#cf5638'];return C[Math.min(C.length-1,Math.floor(f*C.length))];}

/* polar plot (half-polar, one curve per wind speed) as SVG */
function polarSVG(P){
	var W=260,H=260,cx=10,cy=130,maxB=0;P.bsp.forEach(function(r){r.forEach(function(v){if(v>maxB)maxB=v;});});
	if(!maxB)return '';var sc=115/maxB,h='<svg viewBox="0 0 '+W+' '+H+'" width="'+W+'" height="'+H+'" role="img" aria-label="Polar curves">';
	for(var k=2;k<=Math.ceil(maxB);k+=2){h+='<path d="M'+cx+','+(cy-k*sc)+' A'+(k*sc)+','+(k*sc)+' 0 0 1 '+cx+','+(cy+k*sc)+'" fill="none" stroke="currentColor" stroke-opacity=".15"/>';h+='<text x="'+(cx+2)+'" y="'+(cy-k*sc-2)+'" font-size="8" fill="currentColor" fill-opacity=".5">'+k+'</text>';}
	[0,45,90,135,180].forEach(function(a){var r=a*Math.PI/180;h+='<line x1="'+cx+'" y1="'+cy+'" x2="'+(cx+120*Math.sin(r))+'" y2="'+(cy-120*Math.cos(r))+'" stroke="currentColor" stroke-opacity=".12"/>';});
	var COLS=['#5aa6d0','#3f93c9','#2e9e5b','#d9c07a','#e0a13f','#dd7f3a','#cf5638','#b23a2a','#8f2d20','#6b2fa0'];
	P.tws.forEach(function(w,c){var d='';P.twa.forEach(function(a,r){var s=P.bsp[r][c];if(!s)return;var rr=a*Math.PI/180;d+=(d?'L':'M')+(cx+s*sc*Math.sin(rr)).toFixed(1)+','+(cy-s*sc*Math.cos(rr)).toFixed(1);});
		if(d)h+='<path d="'+d+'" fill="none" stroke="'+COLS[c%COLS.length]+'" stroke-width="1.6"><title>'+w+' kt</title></path>';});
	h+='<text x="'+(W-4)+'" y="12" font-size="9" text-anchor="end" fill="currentColor" fill-opacity=".7">'+P.tws.map(function(w){return w;}).join(' / ')+' kt TWS</text>';
	return h+'</svg>';
}
function polarTable(P){
	var h='<table><thead><tr><th>TWA \\ TWS</th>'+P.tws.map(function(w){return '<th>'+w+'</th>';}).join('')+'</tr></thead><tbody>';
	P.twa.forEach(function(a,r){h+='<tr><th>'+a+'°</th>'+P.bsp[r].map(function(v){return '<td>'+(v?v.toFixed(2):'—')+'</td>';}).join('')+'</tr>';});
	return h+'</tbody></table>';
}

/* ---------- state ---------- */
var POLARS=[],TRACKS=[],selPolar=null,windOv=ls('oyc_rt_wind'),sogEma=null,lastLiveT=0,badge=null;
var REC=ls('oyc_rt_rec'),wakeLock=null,recTimer=null,saveDirty=0;

function init(){
	var panel=$('rtPanel');if(!panel)return;
	/* tabs */
	panel.querySelectorAll('.rt-tab').forEach(function(b){b.addEventListener('click',function(){
		panel.querySelectorAll('.rt-tab').forEach(function(x){var on=x===b;x.classList.toggle('on',on);x.setAttribute('aria-selected',on?'true':'false');});
		panel.querySelectorAll('.rt-pane').forEach(function(p){p.classList.toggle('on',p.getAttribute('data-pane')===b.getAttribute('data-tab'));});
		if(b.getAttribute('data-tab')==='archive'&&!TRACKS._loaded)loadTracks();
	});});
	/* badge on the map */
	var mw=document.querySelector('#oycwm .mapwrap');
	if(mw){badge=document.createElement('div');badge.className='rt-badge';badge.setAttribute('aria-live','polite');mw.appendChild(badge);}
	/* yachts + races from the racing hub */
	var ys=$('rtPYacht'),yk=Object.keys(CFG.yachts||{});
	if(yk.length){$('rtPYachtWrap').hidden=false;yk.forEach(function(id){var o=document.createElement('option');o.value=id;o.textContent=CFG.yachts[id];ys.appendChild(o);});}
	var rs=$('rtRaceSel'),rk=Object.keys(CFG.races||{});
	if(rk.length){$('rtRaceWrap').hidden=false;rk.sort(function(a,b){return CFG.races[b].localeCompare(CFG.races[a]);}).forEach(function(id){var o=document.createElement('option');o.value=id;o.textContent=CFG.races[id];rs.appendChild(o);});}
	$('rtBoat').value=ls('oyc_rt_boat')||'';
	$('rtShowPct').checked=ls('oyc_rt_show')!==false;
	$('rtShowPct').addEventListener('change',function(){ls('oyc_rt_show',this.checked);if(this.checked)OYCWM.startLoc();live();});
	$('rtPolarSel').addEventListener('change',function(){selPolar=findPolar(this.value);ls('oyc_rt_polar',this.value||null);
		if(selPolar&&!selPolar.lib&&!$('rtBoat').value)$('rtBoat').value=selPolar.name;libNote();sogEma=null;live();});
	$('rtBoat').addEventListener('change',function(){ls('oyc_rt_boat',this.value.trim());});
	/* wind override */
	$('rtWindSet').addEventListener('click',function(){var s=parseFloat($('rtTws').value),d=parseFloat($('rtTwd').value);
		if(!(s>=0&&s<70)||!(d>=0&&d<=360)){alert('Enter true wind speed (kt) and direction (°M).');return;}
		windOv={tws:s,twdM:d%360};ls('oyc_rt_wind',windOv);windLabel();live();});
	$('rtWindClr').addEventListener('click',function(){windOv=null;ls('oyc_rt_wind',null);$('rtTws').value='';$('rtTwd').value='';windLabel();live();});
	if(windOv){$('rtTws').value=windOv.tws;$('rtTwd').value=windOv.twdM;}
	windLabel();
	/* recorder */
	$('rtRecBtn').addEventListener('click',function(){REC&&REC.active?finishRec():startRec();});
	document.addEventListener('visibilitychange',function(){if(document.visibilityState==='visible'){if(REC&&REC.active){OYCWM.startLoc();lockScreen();}}else saveRec(true);});
	window.addEventListener('pagehide',function(){saveRec(true);});
	if(REC&&REC.active){ /* page was reloaded mid-race: carry on */
		if(REC.boat)$('rtBoat').value=REC.boat;if(REC.race)rs.value=REC.race;
		OYCWM.startLoc();lockScreen();recUI();recTimer=setInterval(recUI,1000);
	}
	renderPending();
	/* polars form */
	$('rtPFile').addEventListener('change',function(){var f=this.files&&this.files[0];if(!f)return;
		if(f.size>512*1024){msg($('rtPMsg'),'That file is too big for a polar table.','err');return;}
		var r=new FileReader();r.onload=function(){$('rtPText').value=r.result;previewPolar();if(!$('rtPName').value)$('rtPName').value=f.name.replace(/\.[^.]+$/,'').replace(/[_-]+/g,' ');};r.readAsText(f);});
	$('rtPText').addEventListener('input',previewPolar);
	(function(){var s=$('rtPLib');if(!s)return;
		s.innerHTML='<option value="">— pick a boat class —</option>'+libBoats().map(function(b){return '<option value="'+esc(b.id)+'">'+esc(b.name)+'</option>';}).join('');
		s.addEventListener('change',function(){var p=libPolar(this.value);if(!p)return;$('rtPText').value=gridText(p.polar);$('rtPScale').value=100;
			var n=$('rtPName');if(!n.value||n.getAttribute('data-auto')){n.value=p.name.replace(/ \(class\)$/,'');n.setAttribute('data-auto','1');}previewPolar();});
		$('rtPName').addEventListener('input',function(){this.removeAttribute('data-auto');});
		$('rtPScaleGo').addEventListener('click',function(){previewPolar();if(!parsed){msg($('rtPMsg'),'Pick a class or paste a table first.','err');return;}
			var k=parseFloat($('rtPScale').value);if(!(k>=70&&k<=120)){msg($('rtPMsg'),'Scale between 70 and 120 %.','err');return;}
			var P={tws:parsed.tws,twa:parsed.twa,bsp:parsed.bsp.map(function(r){return r.map(function(v){return Math.round(v*k)/100;});})};
			$('rtPText').value=gridText(P);$('rtPScale').value=100;previewPolar();msg($('rtPMsg'),'Scaled every speed to '+k+' %. Edit any cell in the table text if you like, then save.','ok');});
	})();
	$('rtPolarForm').addEventListener('submit',savePolar);
	$('rtPCancel').addEventListener('click',resetPolarForm);
	/* GPX import + replay controls */
	$('rtGpx').addEventListener('change',importGpx);
	$('rtRClose').addEventListener('click',closeReplay);
	$('rtRSlider').addEventListener('input',function(){RP&&RP.go(+this.value);});
	$('rtRPlay').addEventListener('click',function(){RP&&RP.toggle();});
	/* live hooks */
	OYCWM._fix=onFix;setInterval(live,2000);
	loadPolars();
}
function libBoats(){return (CFG.lib&&CFG.lib.boats)||[];}
function libPolar(slug){var L=libBoats();for(var i=0;i<L.length;i++)if(L[i].id===slug)return {id:'lib:'+slug,name:L[i].name+' (class)',polar:L[i].polar,lib:true,cert:L[i].cert};return null;}
function findPolar(id){if(id==null||id==='')return null;id=String(id);if(id.indexOf('lib:')===0)return libPolar(id.slice(4));var n=+id;for(var i=0;i<POLARS.length;i++)if(POLARS[i].id===n)return POLARS[i];return null;}
function libNote(){var n=$('rtLibNote');if(!n)return;if(selPolar&&selPolar.lib){n.hidden=false;n.textContent='Class polars from one ORC certificate ('+(selPolar.cert?'sail no. '+selPolar.cert:'ORC')+'). Your boat will differ with sails, bottom and trim: to refine, open Polars → Start from a class, scale or edit the table, and save it as your boat.';}else{n.hidden=true;n.textContent='';}}
function windLabel(){$('rtWindSrc').textContent=windOv?('your instruments: '+windOv.tws+' kt from '+Math.round(windOv.twdM)+'°M'):'forecast model at the boat';}

function loadPolars(){
	return ajax('oyc_rt_polars',{},'GET').then(function(d){POLARS=d.polars||[];renderPolars();}).catch(function(e){$('rtPolarList').innerHTML='<p class="rt-note">'+esc(e.message)+'</p>';});
}
function renderPolars(){
	var sel=$('rtPolarSel'),keep=sel.value||ls('oyc_rt_polar')||'';
	var LB=libBoats();
	sel.innerHTML='<option value="">— none —</option>'
		+(POLARS.length?'<optgroup label="Saved boats">'+POLARS.map(function(p){return '<option value="'+p.id+'">'+esc(p.name)+'</option>';}).join('')+'</optgroup>':'')
		+(LB.length?'<optgroup label="Class polars (ORC)">'+LB.map(function(b){return '<option value="lib:'+esc(b.id)+'">'+esc(b.name)+'</option>';}).join('')+'</optgroup>':'');
	if(findPolar(keep))sel.value=keep;else if(POLARS.length===1)sel.value=POLARS[0].id;
	selPolar=findPolar(sel.value);libNote();
	var L=$('rtPolarList');
	if(!POLARS.length){L.innerHTML='<p class="rt-note">No polars yet. Add your boat’s below: your sail maker, designer or ORC certificate (orc.org → your boat → “Polar data”) will have them.</p>';return;}
	L.innerHTML=POLARS.map(function(p){var P=p.polar||{tws:[],twa:[]};return '<div class="rt-item" data-id="'+p.id+'"><span class="nm">'+esc(p.name)+'</span><span class="meta">'+P.tws.length+' wind speeds ('+(P.tws[0]||0)+'–'+(P.tws[P.tws.length-1]||0)+' kt) × '+P.twa.length+' angles · added by '+esc(p.owner)+'</span>'
		+'<button type="button" class="rt-link" data-do="view">View</button>'+(p.canEdit?'<button type="button" class="rt-link" data-do="edit">Replace</button><button type="button" class="rt-link" data-do="del">Delete</button>':'')+'</div>';}).join('');
	L.querySelectorAll('button').forEach(function(b){b.addEventListener('click',function(){var p=findPolar(b.closest('.rt-item').getAttribute('data-id'));if(!p)return;var d=b.getAttribute('data-do');
		if(d==='view'){$('rtPPrev').innerHTML='<b>'+esc(p.name)+'</b>'+polarSVG(p.polar)+polarTable(p.polar);}
		else if(d==='edit'){$('rtPolarForm').setAttribute('data-id',p.id);$('rtPName').value=p.name;$('rtPYacht').value=p.yacht||'';$('rtPText').value=gridText(p.polar);$('rtPolarFormH').textContent='Replace polars for '+p.name;$('rtPCancel').hidden=false;previewPolar();$('rtPName').focus();}
		else if(d==='del'&&confirm('Delete the polars for '+p.name+'?')){ajax('oyc_rt_polar_delete',{id:p.id}).then(loadPolars).catch(function(e){alert(e.message);});}
	});});
}
function gridText(P){return ['twa/tws'].concat(P.tws).join(';')+'\n'+P.twa.map(function(a,r){return [a].concat(P.bsp[r]).join(';');}).join('\n');}
var parsed=null;
function previewPolar(){
	var t=$('rtPText').value;parsed=null;if(!t.trim()){$('rtPPrev').innerHTML='';msg($('rtPMsg'),'');return;}
	try{parsed=parsePolar(t);$('rtPPrev').innerHTML=polarSVG(parsed)+polarTable(parsed);msg($('rtPMsg'),'Read '+parsed.tws.length+' wind speeds × '+parsed.twa.length+' angles. Check the table, then save.','ok');}
	catch(e){$('rtPPrev').innerHTML='';msg($('rtPMsg'),e.message,'err');}
}
function resetPolarForm(){var f=$('rtPolarForm');f.reset();f.removeAttribute('data-id');$('rtPPrev').innerHTML='';$('rtPolarFormH').textContent='Add your boat’s polars';$('rtPCancel').hidden=true;msg($('rtPMsg'),'');parsed=null;}
function savePolar(e){
	e.preventDefault();previewPolar();
	var name=$('rtPName').value.trim();if(!name){msg($('rtPMsg'),'Give the boat a name.','err');return;}
	if(!parsed){msg($('rtPMsg'),'Add a polar file or paste the table first.','err');return;}
	var b=$('rtPSave');b.disabled=true;
	ajax('oyc_rt_polar_save',{id:$('rtPolarForm').getAttribute('data-id')||0,name:name,yacht:$('rtPYacht').value||0,polar:JSON.stringify(parsed)})
		.then(function(d){ls('oyc_rt_polar',String(d.polar.id));resetPolarForm();msg($('rtPMsg'),'Saved. '+d.polar.name+' is now selected on the Live tab.','ok');return loadPolars();})
		.catch(function(er){msg($('rtPMsg'),er.message,'err');}).then(function(){b.disabled=false;});
}

/* ---------- live target speed ---------- */
function windNow(lat,lon){
	if(windOv)return {tws:windOv.tws,twd:((windOv.twdM-OYCWM.magvar)%360+360)%360,src:'inst'};
	var w=OYCWM.wind(lat,lon,Date.now());return w?{tws:w.tws,twd:w.twd,src:'model'}:null;
}
function onFix(){
	var b=OYCWM.boat();if(!b)return;
	if(b.sog!=null){var now=Date.now(),dt=sogEma==null?0:Math.min(30,(now-lastLiveT)/1000),k=dt?1-Math.exp(-dt/8):1;sogEma=sogEma==null?b.sog:sogEma+(b.sog-sogEma)*k;lastLiveT=now;}
	if(REC&&REC.active)recPoint(b);
	live();
}
function liveCalc(b){
	if(!b||b.sog==null||b.cog==null)return null;
	var w=windNow(b.lat,b.lon);if(!w)return {w:null};
	var twa=twaOf(b.cog,w.twd),tgt=selPolar?polarSpeed(selPolar.polar,w.tws,twa):null,s=sogEma!=null?sogEma:b.sog;
	var pct=(tgt&&tgt>0.3&&s>=1&&w.tws>=2)?s/tgt*100:null;
	return {w:w,twa:twa,tgt:tgt,pct:pct,sog:s};
}
function live(){
	if(!badge)return;
	var show=$('rtShowPct').checked,b=OYCWM.boat(),rec=!!(REC&&REC.active);
	if((!show&&!rec)||!b){badge.className='rt-badge'+(rec?' on recording':'');badge.innerHTML=rec?'<div class="lab">Race log</div><div class="sub">Waiting for GPS…</div><div class="rec">● REC</div>':'';return;}
	var c=liveCalc(b),h;
	if(c&&c.pct!=null&&show){
		h='<div class="lab">Target speed</div><div class="pct">'+Math.round(c.pct)+'%</div><div class="lab">'+bandText(c.pct)+'</div>'
			+'<div class="sub">'+c.sog.toFixed(1)+' / '+c.tgt.toFixed(1)+' kt · TWA '+Math.round(c.twa)+'°</div>';
		var P=selPolar.polar;
		if(c.twa<75){var u=vmgTarget(P,c.w.tws,true);if(u)h+='<div class="sub">Upwind target '+u.bsp.toFixed(1)+' kt @ '+u.twa+'°</div>';}
		else if(c.twa>110){var d=vmgTarget(P,c.w.tws,false);if(d)h+='<div class="sub">Downwind target '+d.bsp.toFixed(1)+' kt @ '+d.twa+'°</div>';}
		h+='<div class="sub">'+(c.w.src==='inst'?'Instrument wind':'Model wind')+' '+c.w.tws.toFixed(0)+' kt</div>';
		badge.className='rt-badge on '+band(c.pct)+(rec?' recording':'');
	}else{
		var why=!show?'':!selPolar?'Pick your boat’s polars':!c||!c.w?'Waiting for wind data…':(c.sog<1?'Moving under 1 kt':'Too little wind for the polars');
		h='<div class="lab">'+(show?'Target speed':'Race log')+'</div><div class="pct">'+(b.sog!=null?(sogEma!=null?sogEma:b.sog).toFixed(1):'—')+'<small style="font-size:.9rem"> kt</small></div>'+(why?'<div class="sub">'+why+'</div>':'');
		badge.className='rt-badge on'+(rec?' recording':'');
	}
	badge.innerHTML=h+'<div class="rec">● REC '+(rec?hms((Date.now()-REC.start)/1000):'')+'</div>';
}

/* ---------- race log recorder ---------- */
function lockScreen(){
	if(!('wakeLock' in navigator))return;
	if(wakeLock&&!wakeLock.released)return;
	navigator.wakeLock.request('screen').then(function(l){wakeLock=l;}).catch(function(){});
}
function startRec(){
	var boat=$('rtBoat').value.trim()||(selPolar&&selPolar.name)||'';
	if(!boat){alert('Enter the boat name first.');$('rtBoat').focus();return;}
	ls('oyc_rt_boat',boat);
	REC={active:true,start:Date.now(),boat:boat,race:$('rtRaceSel').value||'',polar:(selPolar&&!selPolar.lib)?selPolar.id:0,pts:[]};
	saveRec(true);OYCWM.startLoc();lockScreen();
	if(!$('rtShowPct').checked&&selPolar){$('rtShowPct').checked=true;ls('oyc_rt_show',true);}
	recUI();recTimer=setInterval(recUI,1000);live();
}
function recPoint(b){
	var p=REC.pts,t=Math.round((Date.now()-REC.start)/1000),last=p[p.length-1];
	if(last&&(t-last[0]<1||(t-last[0]<10&&distM(last[1],last[2],b.lat,b.lon)<2)))return; /* ≤1 pt/s; skip jitter at rest */
	var c=liveCalc(b)||{},w=c.w||{};
	function r1(v){return v==null||!isFinite(v)?null:Math.round(v*10)/10;}
	p.push([t,Math.round(b.lat*1e6)/1e6,Math.round(b.lon*1e6)/1e6,r1(b.sog),b.cog==null?null:Math.round(b.cog),r1(w.tws),w.twd==null?null:Math.round(w.twd),c.pct==null?null:Math.round(c.pct)]);
	if(++saveDirty>=5)saveRec();
}
function saveRec(force){if(!REC)return;if(force||saveDirty){saveDirty=0;try{localStorage.setItem('oyc_rt_rec',JSON.stringify(REC));}catch(e){/* storage full: keep in memory */}}}
function recUI(){
	var b=$('rtRecBtn'),s=$('rtRecStat');
	if(REC&&REC.active){
		var n=REC.pts.length,last=REC.pts[n-1],gap=last?(Date.now()-REC.start)/1000-last[0]:null;
		b.textContent='■ Finish & save';b.className='rt-stop';
		s.innerHTML='<span class="dot"></span>Recording '+hms((Date.now()-REC.start)/1000)+' · '+n+' points'
			+(!OYCWM.locOn()?' · <b>GPS is off. Turn on My location</b>':(gap!=null&&gap>30?' · no GPS fix for '+Math.round(gap)+'s':''))
			+(!('wakeLock' in navigator)?' · set Auto-Lock to Never so the screen stays on':'');
	}else{b.textContent='● Start race log';b.className='rt-go';s.textContent='';}
}
function finishRec(){
	if(!confirm('Finish the race log and save it to the team archive?'))return;
	clearInterval(recTimer);REC.active=false;
	if(wakeLock){try{wakeLock.release();}catch(e){}wakeLock=null;}
	var job={start:REC.start,boat:REC.boat,race:REC.race,polar:REC.polar,pts:REC.pts,source:'live'};
	REC=null;ls('oyc_rt_rec',null);recUI();live();
	if(job.pts.length<10){alert('Only '+job.pts.length+' GPS points were recorded, which is too few to save.');return;}
	var name=prompt('Name this race (optional):',job.boat+' · '+fmtDate(job.start));
	if(name!=null)job.name=name;
	upload(job);
}
function upload(job){
	var s=$('rtRecStat');s.textContent='Saving to the team archive…';
	return ajax('oyc_rt_track_save',{start:Math.round(job.start/1000),boat:job.boat||'',race:job.race||0,polar:job.polar||0,name:job.name||'',source:job.source||'live',pts:JSON.stringify(job.pts)})
		.then(function(d){s.textContent='Saved to the team archive.';dropPending(job);TRACKS._loaded=false;if($('rtPanel').querySelector('.rt-tab[data-tab=archive]').classList.contains('on'))loadTracks();return d.track;})
		.catch(function(e){addPending(job);s.textContent='Couldn’t save yet ('+e.message+'). It’s kept on this phone; tap Retry when you have signal.';throw e;});
}
function pendingList(){return ls('oyc_rt_pending')||[];}
function addPending(job){var l=pendingList();if(!l.some(function(j){return j.start===job.start;}))l.push(job);ls('oyc_rt_pending',l);renderPending();}
function dropPending(job){ls('oyc_rt_pending',pendingList().filter(function(j){return j.start!==job.start;}));renderPending();}
function renderPending(){
	var el=$('rtPending'),l=pendingList();el.hidden=!l.length;if(!l.length)return;
	el.innerHTML='<b>'+l.length+' race log'+(l.length>1?'s':'')+' waiting to upload</b> (kept on this phone) '
		+l.map(function(j,i){return '<div>'+esc(j.boat||'')+' · '+fmtDate(j.start)+' · '+j.pts.length+' pts <button type="button" class="rt-link" data-i="'+i+'" data-do="retry">Retry</button><button type="button" class="rt-link" data-i="'+i+'" data-do="drop">Discard</button></div>';}).join('');
	el.querySelectorAll('button').forEach(function(b){b.addEventListener('click',function(){var j=pendingList()[+b.getAttribute('data-i')];if(!j)return;
		if(b.getAttribute('data-do')==='retry')upload(j).catch(function(){});else if(confirm('Discard this race log? It can’t be recovered.'))dropPending(j);});});
}

/* ---------- archive ---------- */
function loadTracks(){
	var L=$('rtTrackList');L.innerHTML='<p class="rt-note">Loading…</p>';
	return ajax('oyc_rt_tracks',{},'GET').then(function(d){TRACKS=d.tracks||[];TRACKS._loaded=true;renderTracks();})
		.catch(function(e){L.innerHTML='<p class="rt-note">'+esc(e.message)+'</p>';});
}
function renderTracks(){
	var L=$('rtTrackList');
	if(!TRACKS.length){L.innerHTML='<p class="rt-note">No race logs yet. Start one on the Live tab during a race, or import a GPX track above.</p>';return;}
	L.innerHTML=TRACKS.map(function(t){
		return '<div class="rt-item'+(RP&&RP.id===t.id?' on':'')+'" data-id="'+t.id+'"><span class="nm">'+esc(t.name)+'</span><span class="meta">'+esc(t.boat)+' · '+fmtDate(t.start*1000)+' · '+hms(t.dur)+' · '+t.nm.toFixed(1)+' nm'
			+(t.source==='gpx'?' · GPX':'')+' · by '+esc(t.by)+' · '+(t.share==='racers'?'shared with all racers':'boat crew only')+'</span>'
			+'<button type="button" class="rt-link" data-do="play">Replay</button>'
			+(t.canEdit?'<button type="button" class="rt-link" data-do="share">'+(t.share==='racers'?'Crew only':'Share with all racers')+'</button><button type="button" class="rt-link" data-do="del">Delete</button>':'')+'</div>';}).join('');
	L.querySelectorAll('button').forEach(function(b){b.addEventListener('click',function(){
		var id=+b.closest('.rt-item').getAttribute('data-id'),d=b.getAttribute('data-do'),t=TRACKS.filter(function(x){return x.id===id;})[0];
		if(d==='play')openReplay(id);
		else if(d==='share')ajax('oyc_rt_track_update',{id:id,share:t.share==='racers'?'team':'racers'}).then(loadTracks).catch(function(e){alert(e.message);});
		else if(d==='del'&&confirm('Delete “'+t.name+'” from the archive?'))ajax('oyc_rt_track_update',{id:id,'do':'delete'}).then(function(){if(RP&&RP.id===id)closeReplay();loadTracks();}).catch(function(e){alert(e.message);});
	});});
}

/* GPX → points */
function importGpx(){
	var f=this.files&&this.files[0],inp=this;if(!f)return;
	if(f.size>20*1024*1024){msg($('rtGpxMsg'),'That GPX file is too big.','err');return;}
	var r=new FileReader();r.onload=function(){
		try{
			var x=new DOMParser().parseFromString(r.result,'application/xml'),nodes=x.getElementsByTagName('trkpt');
			if(!nodes.length)nodes=x.getElementsByTagName('rtept');
			var raw=[];for(var i=0;i<nodes.length;i++){var n=nodes[i],tm=n.getElementsByTagName('time')[0],t=tm?Date.parse(tm.textContent):NaN;
				var la=parseFloat(n.getAttribute('lat')),lo=parseFloat(n.getAttribute('lon'));if(isFinite(la)&&isFinite(lo)&&isFinite(t))raw.push([t,la,lo]);}
			if(raw.length<10)throw new Error('No timed track points found in that GPX file.');
			raw.sort(function(a,b){return a[0]-b[0];});
			var t0=raw[0][0],pts=[],prev=null;
			raw.forEach(function(p){var t=Math.round((p[0]-t0)/1000);if(prev&&t-prev[0]<1)return;var sog=null,cog=null;
				if(prev){var dt=t-prev[0],d=distM(prev[1],prev[2],p[1],p[2]);sog=Math.round(d/dt*1.94384*10)/10;cog=d>1?Math.round(brg(prev[1],prev[2],p[1],p[2])):(prev[4]);}
				var row=[t,Math.round(p[1]*1e6)/1e6,Math.round(p[2]*1e6)/1e6,sog,cog,null,null,null];pts.push(row);prev=row;});
			if(pts.length>1){pts[0][3]=pts[1][3];pts[0][4]=pts[1][4];}
			var nm=(x.getElementsByTagName('name')[0]||{}).textContent||f.name.replace(/\.gpx$/i,'');
			var boat=$('rtBoat').value.trim()||(selPolar&&selPolar.name)||'';
			if(!boat){boat=prompt('Which boat is this track from?','')||'';}
			msg($('rtGpxMsg'),'Uploading '+pts.length+' points…');
			upload({start:t0,boat:boat,race:'',polar:(selPolar&&!selPolar.lib)?selPolar.id:0,name:nm.trim(),pts:pts,source:'gpx'})
				.then(function(t){msg($('rtGpxMsg'),'Imported.','ok');setTimeout(function(){openReplay(t.id);},400);})
				.catch(function(e){msg($('rtGpxMsg'),e.message,'err');});
		}catch(e){msg($('rtGpxMsg'),e.message,'err');}
		inp.value='';
	};r.readAsText(f);
}

/* ---------- replay + analysis ---------- */
var RP=null;
/* heading from positions ~win seconds either side (steadier than per-fix COG) */
function smoothCog(P,i,win){
	var a=i,b=i;while(a>0&&P[i][0]-P[a-1][0]<=win)a--;while(b<P.length-1&&P[b+1][0]-P[i][0]<=win)b++;
	if(a===b)return P[i][4];
	return distM(P[a][1],P[a][2],P[b][1],P[b][2])>3?brg(P[a][1],P[a][2],P[b][1],P[b][2]):P[i][4];
}
function avgOver(P,from,to,k){var s=0,n=0;for(var i=0;i<P.length;i++){if(P[i][0]<from)continue;if(P[i][0]>to)break;if(P[i][k]!=null){s+=P[i][k];n++;}}return n?s/n:null;}
function ptAt(P,t){var lo=0,hi=P.length-1;while(lo<hi){var m=(lo+hi)>>1;if(P[m][0]<t)lo=m+1;else hi=m;}return lo;}
function analyse(T,polar,wOv){
	var P=T.pts.filter(function(p){return p&&p.length>=3;}),n=P.length,i;
	/* wind per point: what was logged, or the replay override (GPX / correction) */
	P.forEach(function(p){if(wOv){p.tws=wOv.tws;p.twd=wOv.twd;}else{p.tws=p[5];p.twd=p[6];}
		p.hd=null;p.pct=null;});
	for(i=0;i<n;i++)P[i].hd=smoothCog(P,i,5);
	P.forEach(function(p){if(p.hd!=null&&p.twd!=null){p.twa=twaOf(p.hd,p.twd);}
		if(polar&&p.twa!=null&&p.tws!=null&&p[3]!=null&&p[3]>=1&&p.tws>=2){var t=polarSpeed(polar,p.tws,p.twa);if(t>0.3)p.pct=p[3]/t*100;}
		else if(!polar&&p[7]!=null)p.pct=p[7];});
	/* maneuvers: heading swing > 55° across a 24 s window while moving */
	var ev=[],last=-1e9;
	for(i=0;i<n;i++){
		var t=P[i][0],a=ptAt(P,t-12),b=ptAt(P,t+12);if(b>=n)b=n-1;
		if(P[a].hd==null||P[b].hd==null)continue;
		var sw=Math.abs(angDiff(P[b].hd,P[a].hd)),vb=avgOver(P,t-40,t-12,3),wd=P[i].twd,cross=false;
		/* with wind known, a smaller swing still counts if the bow (or stern) crossed the wind:
		   deep-running gybes are often only 25-40 degrees */
		if(wd!=null){var sa0=angDiff(P[a].hd,wd),sb0=angDiff(P[b].hd,wd);cross=(sa0>0)!==(sb0>0)&&Math.abs(sa0)>8&&Math.abs(sb0)>8&&Math.abs(sa0)<172&&Math.abs(sb0)<172;}
		if((sw<55&&!(cross&&sw>=20))||!(vb>=1.5)||t-last<35)continue;
		/* settle on the point of fastest turn inside the window */
		var bestI=i,bestR=0;for(var j=a+1;j<=b;j++){if(P[j].hd==null||P[j-1].hd==null)continue;var r=Math.abs(angDiff(P[j].hd,P[j-1].hd))/Math.max(1,P[j][0]-P[j-1][0]);if(r>bestR){bestR=r;bestI=j;}}
		var tc=P[bestI][0],before=P[ptAt(P,tc-15)],after=P[Math.min(n-1,ptAt(P,tc+15))],kind='Turn';
		var twd=P[bestI].twd;
		if(twd!=null&&before.hd!=null&&after.hd!=null){var ta=twaOf(before.hd,twd),tb=twaOf(after.hd,twd);
			if(ta<90&&tb<90)kind='Tack';else if(ta>90&&tb>90)kind='Gybe';else kind=(ta<tb)?'Bear away':'Round up';
			/* a real tack/gybe crosses the wind: sign of the TWA flips */
			if(kind==='Tack'||kind==='Gybe'){var sa=angDiff(before.hd,twd),sb=angDiff(after.hd,twd);if((sa>0)===(sb>0))kind='Turn';}}
		/* distance lost vs. holding the entry speed for 40 s */
		var v0=avgOver(P,tc-40,tc-10,3)||0,d=0,minS=99;
		for(j=ptAt(P,tc-10);j<n&&P[j][0]<=tc+30;j++){if(j>0)d+=distM(P[j-1][1],P[j-1][2],P[j][1],P[j][2]);if(P[j][3]!=null&&P[j][3]<minS)minS=P[j][3];}
		var lostM=Math.max(0,v0/1.94384*Math.min(40,P[Math.min(n-1,j-1)][0]-P[ptAt(P,tc-10)][0])-d);
		ev.push({k:kind,t:tc,i:bestI,lat:P[bestI][1],lon:P[bestI][2],v0:v0,min:minS===99?null:minS,lost:lostM,sw:Math.round(sw)});
		last=tc;i=b;
	}
	/* speed drops: under 75% of the past minute’s median for 8 s+, away from maneuvers */
	var inDrop=null;
	function nearEv(t){return ev.some(function(e){return Math.abs(e.t-t)<35&&e.k!=='Drop';});}
	for(i=0;i<n;i++){
		var tt=P[i][0],s=P[i][3];if(s==null)continue;
		var win=[];for(j=ptAt(P,tt-70);j<i&&P[j][0]<tt-10;j++)if(P[j][3]!=null)win.push(P[j][3]);
		if(win.length<8){continue;}win.sort(function(x,y){return x-y;});var med=win[win.length>>1];
		var low=med>=2&&s<med*0.75;
		if(low&&!inDrop)inDrop={t:tt,i:i,med:med,min:s};
		else if(low&&inDrop){if(s<inDrop.min)inDrop.min=s;}
		else if(!low&&inDrop){if(tt-inDrop.t>=8&&!nearEv(inDrop.t))ev.push({k:'Speed drop',t:inDrop.t,i:inDrop.i,lat:P[inDrop.i][1],lon:P[inDrop.i][2],v0:inDrop.med,min:inDrop.min,dur:tt-inDrop.t});inDrop=null;}
	}
	ev.sort(function(a,b){return a.t-b.t;});
	/* totals */
	var nm=0,mx=0,ss=0,sn=0,ps=0,pn=0;for(i=1;i<n;i++)nm+=distM(P[i-1][1],P[i-1][2],P[i][1],P[i][2])/1852;
	P.forEach(function(p){if(p[3]!=null){if(p[3]>mx)mx=p[3];if(p[3]>=0.5){ss+=p[3];sn++;}}if(p.pct!=null&&p.pct<150){ps+=p.pct;pn++;}});
	function cnt(k){return ev.filter(function(e){return e.k===k;}).length;}
	function avgLost(k){var l=ev.filter(function(e){return e.k===k;});return l.length?l.reduce(function(a,e){return a+e.lost;},0)/l.length:null;}
	return {P:P,ev:ev,nm:nm,dur:n?P[n-1][0]:0,max:mx,avg:sn?ss/sn:0,pct:pn?ps/pn:null,tacks:cnt('Tack'),gybes:cnt('Gybe'),drops:cnt('Speed drop'),turns:cnt('Turn')+cnt('Bear away')+cnt('Round up'),tackLoss:avgLost('Tack'),gybeLoss:avgLost('Gybe'),hasWind:P.some(function(p){return p.twd!=null;})};
}
function openReplay(id){
	var box=$('rtReplay');box.hidden=false;$('rtRName').textContent='Loading…';$('rtRStats').innerHTML='';$('rtREvents').innerHTML='';
	ajax('oyc_rt_track_get',{id:id},'GET').then(function(d){closeReplay(true);startReplay(d.track);}).catch(function(e){$('rtRName').textContent=e.message;});
}
function closeReplay(keepBox){
	if(RP){RP.stop();if(RP.layer)OYCWM.map.removeLayer(RP.layer);}
	RP=null;if(keepBox!==true)$('rtReplay').hidden=true;
	document.querySelectorAll('#rtTrackList .rt-item.on').forEach(function(e){e.classList.remove('on');});
}
function startReplay(T){
	var map=OYCWM.map,pol=findPolar(T.polar)||selPolar,polar=pol?pol.polar:null,wOv=null;
	var A=analyse(T,polar,null);
	if(!A.hasWind)wOv=ls('oyc_rt_rwind_'+T.id);
	if(wOv)A=analyse(T,polar,wOv);
	var layer=L.layerGroup().addTo(map),P=A.P;
	RP={id:T.id,layer:layer,A:A,cur:0,timer:null};
	function draw(){
		layer.clearLayers();
		var lo=Infinity,hi=0;P.forEach(function(p){if(p[3]!=null&&p[3]>=0.5){if(p[3]<lo)lo=p[3];if(p[3]>hi)hi=p[3];}});
		var byPct=A.pct!=null,seg=[],col=null;
		for(var i=0;i<P.length;i++){var c=byPct?pctColor(P[i].pct):spdColor(P[i][3]||0,lo,hi);
			if(c!==col&&seg.length){L.polyline(seg,{color:col,weight:4,opacity:.95,interactive:false}).addTo(layer);seg=[seg[seg.length-1]];}
			col=c;seg.push([P[i][1],P[i][2]]);}
		if(seg.length>1)L.polyline(seg,{color:col,weight:4,opacity:.95,interactive:false}).addTo(layer);
		A.ev.forEach(function(e){var m={Tack:['T','#1960aa'],Gybe:['G','#6b2fa0'],'Speed drop':['▼','#b23a2a']}[e.k]||['↻','#5a6b7d'];
			L.marker([e.lat,e.lon],{icon:L.divIcon({className:'rt-mk',html:'<span style="background:'+m[1]+'">'+m[0]+'</span>',iconSize:[18,18],iconAnchor:[9,9]}),zIndexOffset:900})
				.bindTooltip(evLine(e,true),{direction:'top'}).on('click',function(){RP.go(e.i);}).addTo(layer);});
		RP.boat=L.circleMarker([P[0][1],P[0][2]],{radius:7,color:'#fff',weight:2,fillColor:'#0b2a4a',fillOpacity:1}).addTo(layer);
		$('rtRLegend').innerHTML=byPct
			?'<span><i style="background:#8f2d20"></i>&lt;85%</span><span><i style="background:#cf5638"></i>85–90</span><span><i style="background:#e0a13f"></i>90–95</span><span><i style="background:#d9c07a"></i>95–100</span><span><i style="background:#2e9e5b"></i>100–105</span><span><i style="background:#1960aa"></i>&gt;105% of target</span>'
			:'<span>Track coloured by speed: <i style="background:#3b4cc0"></i>slowest … <i style="background:#cf5638"></i>fastest ('+lo.toFixed(1)+'–'+hi.toFixed(1)+' kt)</span>'+(polar?'':' <span>Pick polars on the Live tab to colour by % of target.</span>');
	}
	function stats(){
		function st(v,l){return '<div class="rt-stat"><b>'+v+'</b><span>'+l+'</span></div>';}
		var h=st(hms(A.dur),'Duration')+st(A.nm.toFixed(2)+' nm','Distance')+st(A.avg.toFixed(1)+' kt','Avg SOG')+st(A.max.toFixed(1)+' kt','Top SOG');
		if(A.pct!=null)h+=st(Math.round(A.pct)+'%','Avg of target');
		h+=st(A.tacks+(A.tackLoss!=null?' <small>(~'+Math.round(A.tackLoss)+' m each)</small>':''),'Tacks')+st(A.gybes+(A.gybeLoss!=null?' <small>(~'+Math.round(A.gybeLoss)+' m each)</small>':''),'Gybes')+st(A.drops,'Speed drops')+(A.turns?st(A.turns,A.hasWind||wOv?'Mark turns':'Turns'):'');
		if(!A.hasWind&&!wOv)h+='<div class="rt-stat" style="grid-column:1/-1"><span>No wind in this log (GPX import), so turns can’t be split into tacks and gybes.</span> <span style="text-transform:none">Wind during the race: <input type="number" id="rtRTws" min="0" max="60" step="1" placeholder="kt" style="width:4.5em"> kt from <input type="number" id="rtRTwd" min="0" max="359" placeholder="°M" style="width:4.5em">°M <button type="button" class="rt-sec" id="rtRWset">Apply</button></span></div>';
		else if(wOv)h+='<div class="rt-stat" style="grid-column:1/-1"><span style="text-transform:none">Using wind '+wOv.tws+' kt from '+Math.round((wOv.twd+OYCWM.magvar)%360)+'°M for this replay. <button type="button" class="rt-link" id="rtRWclr">Clear</button></span></div>';
		$('rtRStats').innerHTML=h;
		var ws=$('rtRWset');if(ws)ws.addEventListener('click',function(){var s=parseFloat($('rtRTws').value),d=parseFloat($('rtRTwd').value);if(!(s>=0)||!(d>=0)){return;}
			wOv={tws:s,twd:((d-OYCWM.magvar)%360+360)%360};ls('oyc_rt_rwind_'+T.id,wOv);rebuild();});
		var wc=$('rtRWclr');if(wc)wc.addEventListener('click',function(){wOv=null;ls('oyc_rt_rwind_'+T.id,null);rebuild();});
	}
	function evDetail(e){
		return e.k==='Speed drop'?e.v0.toFixed(1)+' → '+(e.min!=null?e.min.toFixed(1):'?')+' kt for '+Math.round(e.dur)+'s'
			:e.v0.toFixed(1)+' kt in, low '+(e.min!=null?e.min.toFixed(1):'?')+' kt, ~'+Math.round(e.lost)+' m lost';
	}
	function evLine(e,plain){var s=e.k+' at '+clock((T.start+e.t)*1000)+': '+evDetail(e);return plain?esc(s):s;}
	function events(){
		var el=$('rtREvents');
		if(!A.ev.length){el.innerHTML='<p class="rt-note">No tacks, gybes or speed drops found.</p>';return;}
		el.innerHTML=A.ev.map(function(e,k){return '<div class="rt-ev" data-k="'+k+'"><span class="k">'+esc(e.k)+'</span><span class="t">+'+hms(e.t)+'</span><span>'+esc(evDetail(e))+'</span></div>';}).join('');
		el.querySelectorAll('.rt-ev').forEach(function(r){r.addEventListener('click',function(){var e=A.ev[+r.getAttribute('data-k')];RP.go(e.i);map.setView([e.lat,e.lon],Math.max(map.getZoom(),15));});});
	}
	function rebuild(){A=analyse(T,polar,wOv);P=A.P;RP.A=A;draw();stats();events();RP.go(RP.cur);}
	RP.go=function(i){i=Math.max(0,Math.min(P.length-1,i|0));RP.cur=i;var p=P[i];$('rtRSlider').value=i;
		if(RP.boat)RP.boat.setLatLng([p[1],p[2]]);
		$('rtRNow').innerHTML=clock((T.start+p[0])*1000)+' · '+(p[3]!=null?p[3].toFixed(1):'—')+' kt · '+(p.hd!=null?Math.round((p.hd+OYCWM.magvar)%360)+'°M':'—')
			+(p.twa!=null?' · TWA '+Math.round(p.twa)+'°':'')+(p.pct!=null?' · <b>'+Math.round(p.pct)+'%</b>':'');};
	RP.stop=function(){if(RP.timer){clearInterval(RP.timer);RP.timer=null;}$('rtRPlay').innerHTML='&#9654;';};
	RP.toggle=function(){if(RP.timer){RP.stop();return;}if(RP.cur>=P.length-1)RP.go(0);$('rtRPlay').innerHTML='&#10074;&#10074;';
		var tSim=P[RP.cur][0];RP.timer=setInterval(function(){tSim+=+$('rtRSpeed').value*0.1;var j=RP.cur;while(j<P.length-1&&P[j+1][0]<=tSim)j++;RP.go(j);if(j>=P.length-1)RP.stop();},100);};
	$('rtRSlider').max=P.length-1;$('rtRName').textContent=T.name+' · '+T.boat+' · '+fmtDate(T.start*1000)+(pol?' · polars: '+pol.name:'');
	draw();stats();events();RP.go(0);
	try{var bb=L.latLngBounds(P.map(function(p){return [p[1],p[2]];}));map.fitBounds(bb,{padding:[30,30]});}catch(e){}
	var it=document.querySelector('#rtTrackList .rt-item[data-id="'+T.id+'"]');if(it)it.classList.add('on');
	var mw=document.querySelector('#oycwm .mapwrap');if(mw&&mw.scrollIntoView)mw.scrollIntoView({behavior:'smooth',block:'center'});
}

/* start once everything above is defined (vars are hoisted but not yet assigned) */
function boot(){if(!window.OYCWM||!window.L){return setTimeout(boot,300);}init();}
if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',boot);else boot();

/* exposed for tests */
window.OYC_RT_TEST={parsePolar:parsePolar,polarSpeed:polarSpeed,vmgTarget:vmgTarget,analyse:analyse};
})();

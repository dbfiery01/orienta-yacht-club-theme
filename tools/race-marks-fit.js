// Nodes: A0 B1 C2 D3 E4 F5 G6 H7 I8 J9 ; A is the anchor (NOAA G"1" can)
const NAME=['A','B','C','D','E','F','G','H','I','J'];
const Alat=40.92220, Alon=-73.72070, cosL=Math.cos(Alat*Math.PI/180);
// Table 4 legs: [from,to,dist_nm,mag_bearing]
const L=[[0,1,0.8,92],[0,2,1.6,75],[0,3,3.9,115],[0,8,2.0,215],[0,9,1.4,230],
[1,2,0.9,59],[1,3,3.1,120],[2,3,2.8,137],[3,4,2.1,221],[4,5,1.3,228],[5,4,1.3,48],
[6,7,1.0,246],[6,8,1.0,329],[7,8,1.3,16],[8,9,0.7,5],[3,2,2.8,317]];
const R=x=>x*Math.PI/180;
// build vector components (East, North) in nm for each leg
const legs=L.map(([f,t,d,bm])=>{const bt=R(bm-13);return[f,t,d*Math.sin(bt),d*Math.cos(bt)];});
// least squares on a graph, x and y independent. Free nodes 1..9 (A=0 fixed at origin)
function solve(comp){ // comp: 2=East(dE col3) 3=North(dN col4)
  const n=9; const M=Array.from({length:n},()=>new Array(n).fill(0)); const c=new Array(n).fill(0);
  for(const lg of legs){const f=lg[0],t=lg[1],val=lg[comp];
    // objective (x_t - x_f - val)^2 ; free index = node-1 (node0 fixed 0)
    const fi=f-1, ti=t-1;
    if(ti>=0){M[ti][ti]+=1; if(fi>=0)M[ti][fi]-=1; c[ti]+=val;}
    if(fi>=0){M[fi][fi]+=1; if(ti>=0)M[fi][ti]-=1; c[fi]-=val;}
  }
  // Gaussian elimination
  for(let i=0;i<n;i++){let p=i;for(let r=i+1;r<n;r++)if(Math.abs(M[r][i])>Math.abs(M[p][i]))p=r;[M[i],M[p]]=[M[p],M[i]];[c[i],c[p]]=[c[p],c[i]];
    for(let r=0;r<n;r++){if(r!==i&&M[r][i]!==0){const k=M[r][i]/M[i][i];for(let cc=i;cc<n;cc++)M[r][cc]-=k*M[i][cc];c[r]-=k*c[i];}}}
  return c.map((v,i)=>v/M[i][i]);
}
const X=[0,...solve(2)]; // East nm
const Y=[0,...solve(3)]; // North nm
const pos=NAME.map((nm,i)=>({nm,lat:+(Alat+Y[i]/60).toFixed(5),lon:+(Alon+X[i]/(60*cosL)).toFixed(5)}));
console.log('=== fitted positions ===');
pos.forEach(p=>console.log(`${p.nm}: lat:${p.lat}, lon:${p.lon}`));
// verify all legs
function dist(a,b){const dlat=(b.lat-a.lat)*60,dlon=(b.lon-a.lon)*60*cosL;return Math.hypot(dlat,dlon);}
function brgM(a,b){const y=Math.sin(R(b.lon-a.lon))*Math.cos(R(b.lat));const x=Math.cos(R(a.lat))*Math.sin(R(b.lat))-Math.sin(R(a.lat))*Math.cos(R(b.lat))*Math.cos(R(b.lon-a.lon));return Math.round(((Math.atan2(y,x)*180/Math.PI)+13+360)%360);}
console.log('\n=== leg check (fitted vs Table 4) ===');let maxd=0,maxb=0;
for(const [f,t,d,bm] of L){const a=pos[f],b=pos[t];const md=dist(a,b),mb=brgM(a,b);const dd=md-d,db=Math.min(Math.abs(mb-bm),360-Math.abs(mb-bm));maxd=Math.max(maxd,Math.abs(dd));maxb=Math.max(maxb,db);
  console.log(`${NAME[f]}>${NAME[t]}  ${mb}°/${md.toFixed(2)}nm  (tbl ${bm}°/${d}nm)  Δ${db}°/${dd.toFixed(2)}nm`);}
console.log(`\nworst: ${maxd.toFixed(2)} nm, ${maxb}°`);

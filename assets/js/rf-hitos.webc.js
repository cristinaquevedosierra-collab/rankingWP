
/*! rf-hitos.webc.js — Web Component <rf-hitos>
 *  Aísla la pestaña “Hitos” en Shadow DOM y consume SOLO:
 *   - GET /api/Jugador/GetCampeonesEspania
 *   - GET /api/Ranking/GetRankingPorModalidadPorTemporadaESPGlicko2/{ModalidadId}/{TemporadaId}
 *  Opcional: login previo con /api/Seguridad/login (usuario/password)
 */
class RFHitos extends HTMLElement{
  constructor(){
    super();
    this.attachShadow({mode:'open'});
  }
  connectedCallback(){
    const YEAR_BASE = 2011; // 2011->1
    const baseUrl = (this.getAttribute('base-url')||'').replace(/\/$/,'');
    const jugadorId = this.getAttribute('jugador-id');
  const apiUser = this.getAttribute('auth-user') || "";
  const apiPass = this.getAttribute('auth-pass') || "";
  const authTokenAttr = this.getAttribute('auth-token') || "";
    if(!baseUrl || !jugadorId){
      this.shadowRoot.innerHTML = `<div style="font:14px/1.4 system-ui">Hitos: faltan atributos <b>base-url</b> y/o <b>jugador-id</b>.</div>`;
      return;
    }

    const style = document.createElement('style');
    style.textContent = `
      :host{all:initial; font-family:Inter,system-ui,Segoe UI,Roboto,Helvetica,Arial,sans-serif; color:#111; display:block}
      .rf-hitos__title{font-weight:800;font-size:20px;margin:10px 0 14px;background:linear-gradient(135deg,#ffe7a3,#d4af37);
        -webkit-background-clip:text;background-clip:text;color:transparent;border-bottom:3px solid #d4af37;padding-bottom:8px}
      .rf-hitos__grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px}
      @media (max-width:780px){.rf-hitos__grid{grid-template-columns:1fr}}
  .rf-card{position:relative;border-radius:16px;box-shadow:0 6px 18px rgba(0,0,0,.12);overflow:hidden;background:#fff}
      .rf-card.is-empty{--rf-fold:28px;
        --rf-fold-hl: rgba(255,255,255,0.96);
        --rf-fold-mid: rgba(244,247,251,0.82);
        --rf-fold-shadow: rgba(0,0,0,0.10);
        --rf-fold-back1: #e9eef5; --rf-fold-back2:#d6dde7; --rf-fold-tint: rgba(0,0,0,0);
      }
      .tone-gold.is-empty{ --rf-fold-hl: rgba(255,252,240,0.98); --rf-fold-mid: rgba(255,246,210,0.86); --rf-fold-back1:#fff7cf; --rf-fold-back2:#ffe089; --rf-fold-tint: rgba(212,175,55,0.08) }
      .tone-silver.is-empty{ --rf-fold-hl: rgba(255,255,255,0.98); --rf-fold-mid: rgba(242,246,252,0.86); --rf-fold-back1:#f1f5fb; --rf-fold-back2:#dce3ee; --rf-fold-tint: rgba(156,170,186,0.08) }
  .tone-bronze.is-empty{ --rf-fold-hl: rgba(255,255,255,0.98); --rf-fold-mid: rgba(252,252,252,0.86); --rf-fold-back1:#fff6ef; --rf-fold-back2:#ffe9d9; --rf-fold-tint: rgba(184,115,51,0.04) }
      .rf-card__frame{padding:14px 14px 16px}
      /* Empty-state aesthetics: diffuse stripes behind, dashed frame and folded corner on container */
      .rf-card.is-empty .rf-card__frame{position:relative;overflow:hidden;z-index:2;display:flex;align-items:center;justify-content:center;text-align:center;min-height:200px;
        opacity:.5; filter:saturate(0.75) contrast(0.96) brightness(1.02)}
      .rf-card.is-empty .rf-card__frame::before{content:"";position:absolute;inset:0;z-index:0;pointer-events:none;
        background:repeating-linear-gradient(135deg, rgba(102,112,133,0.16) 0 10px, rgba(102,112,133,0.06) 10px 30px);
        filter:blur(1px);transform:rotate(-1deg) scale(1.02);opacity:.92}
      .rf-card.is-empty .rf-card__frame::after{content:"";position:absolute;inset:12px;pointer-events:none;border:2px dashed rgba(107,114,128,0.38);border-radius:14px;z-index:2;mix-blend-mode:multiply}
      /* pliegue con curvatura + cara trasera (pegatina despegada) */
      .rf-card.is-empty::before{content:"";position:absolute;top:0;right:0;width:var(--rf-fold);height:var(--rf-fold);z-index:4;pointer-events:none;
        background:
          linear-gradient(225deg, rgba(0,0,0,0.22) 0 1px, var(--rf-fold-hl) 1px 2px, rgba(255,255,255,0) 2px) no-repeat,
          conic-gradient(from 210deg at 100% 0,
            var(--rf-fold-shadow) 0deg,
            var(--rf-fold-hl) 40deg,
            var(--rf-fold-mid) 75deg,
            rgba(0,0,0,0.12) 110deg,
            rgba(0,0,0,0.06) 180deg
          ),
          linear-gradient(180deg, var(--rf-fold-tint), var(--rf-fold-tint));
  background-size:100% 100%, 100% 100%, 100% 100%;
  background-blend-mode:normal, normal, multiply;
        clip-path:polygon(100% 0, 0 100%, 100% 100%);
  -webkit-mask: radial-gradient(100% 100% at 100% 0, transparent 0 12px, black 14px);
  mask: radial-gradient(100% 100% at 100% 0, transparent 0 12px, black 14px);
  box-shadow:-1px 1px 0 rgba(255,255,255,0.85) inset, 0 4px 8px rgba(0,0,0,0.18);
  filter: drop-shadow(-6px 8px 12px rgba(0,0,0,0.18));
        border-top-right-radius:10px;opacity:.98}
      .rf-card.is-empty::after{content:"";position:absolute;top:0;right:0;width:calc(var(--rf-fold) - 2px);height:calc(var(--rf-fold) - 2px);z-index:3;pointer-events:none;
        background:linear-gradient(225deg, #e9eef5 0%, #d6dde7 100%);
        clip-path:polygon(100% 8%, 8% 100%, 100% 100%);
        filter:blur(.2px) saturate(.92);
        opacity:.85; transform:translate(-2px, 2px);
      }
      .tone-gold .rf-card__frame{background:linear-gradient(180deg,#fff3c4 0%,#ffd77a 100%);border:1px solid #d4af37}
      .tone-silver .rf-card__frame{background:linear-gradient(180deg,#f8fafc 0%,#e8edf3 100%);border:1px solid #b7bcc4}
      .tone-bronze .rf-card__frame{background:linear-gradient(180deg,#ffe1c7 0%,#f5b986 100%);border:1px solid #b87333}
      .rf-card__title{margin:0 0 10px;font-weight:800;color:#2a2a2a;display:flex;align-items:center;gap:8px}
      .rf-card__title .cnt{opacity:.8;font-weight:900}
      .rf-badges{display:flex;flex-wrap:wrap;gap:10px;list-style:none;margin:0;padding:0}
      .pill{display:inline-flex;align-items:center;gap:8px;padding:8px 12px;border-radius:9999px;border:1px solid transparent}
      .pill.gold{background:linear-gradient(180deg,#fff7cf,#ffe089);border-color:#d4af37;color:#2b1f00}
      .pill.silver{background:linear-gradient(180deg,#ffffff,#e7ecf2);border-color:#b7bcc4;color:#1f2833}
      .pill.bronze{background:linear-gradient(180deg,#ffe8d2,#f3bc8e);border-color:#b87333;color:#2a1604}
      .tile{display:flex;flex-direction:column;align-items:center;justify-content:flex-start;width:110px;min-height:110px;
            padding:10px 8px;border-radius:18px;border:1px solid #d4af37;text-align:center;
            background:linear-gradient(180deg,#fff7cf,#ffe089);color:#2b1f00}
      .ico{width:44px;height:44px;margin-bottom:4px}
      .ico.double{position:relative}
      .ico.double svg:last-child{position:absolute;left:14px;top:0;transform:rotate(-8deg)}
      .rf-empty{color:#5c6b7c;font-size:13px;font-weight:600;opacity:.9}
      /* Reset visual: sin pliegue; mantener dashed y barras difusas */
      .rf-card.is-empty::before,.rf-card.is-empty::after{content:none !important;display:none !important;box-shadow:none !important;filter:none !important}
      .rf-card.is-empty .rf-card__frame::before{content:"";position:absolute;inset:0;z-index:0;pointer-events:none;
        background:repeating-linear-gradient(135deg, rgba(102,112,133,0.06) 0 6px, rgba(102,112,133,0.02) 6px 16px);
        filter:blur(1px);transform:rotate(-1deg) scale(1.02);opacity:.85}
  .rf-sticker .rf-corner{display:none !important}
  /* Sticker bloque para retos pendientes — apariencia pegatina */
  .rf-sticker{position:relative;display:inline-flex;align-items:center;gap:10px;padding:10px 14px;border-radius:14px;margin:6px 0 10px;
  background:linear-gradient(180deg,rgba(255,255,255,0.84),rgba(246,248,252,0.78));
  border:1.3px dashed rgba(156,163,175,0.38);color:#1f2937;font-weight:800;letter-spacing:.3px;transform:rotate(-1deg);
  -webkit-backdrop-filter:saturate(1.02) blur(3.5px); backdrop-filter:saturate(1.02) blur(3.5px);}
  .rf-tape{position:absolute;top:-8px;right:12px;width:36px;height:14px;transform:rotate(8deg);
       background:linear-gradient(180deg, rgba(250,250,250,0.7) 0%, rgba(245,245,245,0.45) 100%);
       box-shadow:0 2px 4px rgba(0,0,0,0.12);border-radius:3px}
  .rf-sticker{--rf-stk-fold:18px; --rf-stk-hl: rgba(255,255,255,0.96); --rf-stk-mid: rgba(244,247,251,0.82); --rf-stk-back1:#e9eef5; --rf-stk-back2:#d6dde7; --rf-stk-tint: rgba(0,0,0,0)}
  .tone-gold .rf-sticker{ --rf-stk-hl: rgba(255,252,240,0.98); --rf-stk-mid: rgba(255,246,210,0.86); --rf-stk-back1:#fff7cf; --rf-stk-back2:#ffe089; --rf-stk-tint: rgba(212,175,55,0.06) }
  .tone-silver .rf-sticker{ --rf-stk-hl: rgba(255,255,255,0.98); --rf-stk-mid: rgba(242,246,252,0.86); --rf-stk-back1:#f1f5fb; --rf-stk-back2:#dce3ee; --rf-stk-tint: rgba(156,170,186,0.06) }
  .tone-bronze .rf-sticker{ --rf-stk-hl: rgba(255,255,255,0.98); --rf-stk-mid: rgba(252,252,252,0.86); --rf-stk-back1:#fff6ef; --rf-stk-back2:#ffe9d9; --rf-stk-tint: rgba(184,115,51,0.04) }
  .rf-corner{position:absolute;top:-1px;right:-1px;width:var(--rf-stk-fold);height:var(--rf-stk-fold);z-index:2;pointer-events:none;
    background:
      linear-gradient(225deg, rgba(0,0,0,0.24) 0 1px, var(--rf-stk-hl) 1px 2px, rgba(255,255,255,0) 2px) no-repeat,
      conic-gradient(from 210deg at 100% 0,
        rgba(0,0,0,0.10) 0deg,
        var(--rf-stk-hl) 40deg,
        var(--rf-stk-mid) 75deg,
        rgba(0,0,0,0.12) 110deg,
        rgba(0,0,0,0.06) 180deg
      ),
      linear-gradient(180deg, var(--rf-stk-tint), var(--rf-stk-tint));
    background-blend-mode:normal, normal, multiply;
    clip-path:polygon(100% 0, 0 100%, 100% 100%);
    -webkit-mask: radial-gradient(100% 100% at 100% 0, transparent 0 8px, black 10px);
    mask: radial-gradient(100% 100% at 100% 0, transparent 0 8px, black 10px);
    box-shadow:-1px 1px 0 rgba(255,255,255,0.75) inset, 0 3px 6px rgba(0,0,0,0.14); filter: drop-shadow(-4px 5px 8px rgba(0,0,0,0.16)); opacity:.96}
  .rf-corner::after{content:"";position:absolute;inset:0;transform:translate(-2px,2px);z-index:-1;
    background:linear-gradient(225deg, #e9eef5 0%, #d6dde7 100%);
    clip-path:polygon(100% 10%, 10% 100%, 100% 100%);
    filter:blur(.2px) saturate(.92);opacity:.85}
  .rf-sticker__icon{display:inline-flex;width:22px;height:22px;color:#6b7280;opacity:.8}
  .rf-sticker__icon svg{width:22px;height:22px;opacity:.75;filter:drop-shadow(0 2px 4px rgba(0,0,0,0.14)) saturate(0.85) blur(0.25px);mix-blend-mode:multiply}
  .rf-sticker__icon svg *{fill:none !important;stroke:rgba(71,85,105,0.58) !important;stroke-width:1.5;stroke-linecap:round;stroke-linejoin:round}
  .rf-sticker__text{font-size:13px;text-transform:uppercase;color:rgba(31,41,55,0.58);text-shadow:0 1px 0 rgba(255,255,255,0.55)}
      svg{display:block}
    `;

    const tpl = document.createElement('template');
    tpl.innerHTML = `
      <section>
        <h2 class="rf-hitos__title">Hitos</h2>
        <div class="rf-hitos__grid" id="grid">
          ${['Campeón de España (Dobles)','Campeón de España (Individual)',
             'Nº1 del Ranking por Temporada (Dobles)','Nº1 del Ranking por Temporada (Individual)',
             'Nº2 del Ranking por Temporada (Dobles)','Nº2 del Ranking por Temporada (Individual)',
             'Nº3 del Ranking por Temporada (Dobles)','Nº3 del Ranking por Temporada (Individual)']
            .map((t,i)=>`<article class="rf-card tone-${['gold','gold','gold','gold','silver','silver','bronze','bronze'][i]}">
                           <div class="rf-card__frame">
                             <h3 class="rf-card__title"><span class="t">${t}</span> <span class="cnt" id="c${i}">×0</span></h3>
                             <div class="rf-sticker rf-sticker--locked" id="s${i}" hidden>
                               <span class="rf-tape" aria-hidden="true"></span>
                               <span class="rf-corner" aria-hidden="true"></span>
                               <span class="rf-sticker__icon" aria-hidden="true"></span>
                               <span class="rf-sticker__text">Pendiente de conseguir</span>
                             </div>
                             <div class="rf-empty" id="e${i}">El jugador no tiene hitos de esta categoría</div>
                             <ul class="rf-badges" id="b${i}" role="list" hidden></ul>
                           </div>
                         </article>`).join('')}
        </div>
      </section>
    `;
    this.shadowRoot.append(style, tpl.content.cloneNode(true));

    const star  = `<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2l3.1 6.3 6.9 1-5 4.9 1.2 7.9L12 18.6 5.8 22l1.2-7.9-5-4.9 6.9-1 3.1-6.3z"/></svg>`;
    const crown = `<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M3 7l4.5 4 4.5-7 4.5 7L21 7v10H3z"/></svg>`;
    const medal = `<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><circle cx="12" cy="10" r="6"/><path d="M7 20l5-3 5 3-5-10z"/></svg>`;
    const lock  = `<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M6 10V8a6 6 0 1112 0v2h1a1 1 0 011 1v10a1 1 0 01-1 1H5a1 1 0 01-1-1V11a1 1 0 011-1h1zm2 0h8V8a4 4 0 10-8 0v2z"/></svg>`;

    const toSeasonLabel = (raw)=>{
      if(raw==null) return null;
      const s = String(raw);
      const mY = s.match(/(20\d{2})/);
      if(mY){ const n = parseInt(mY[1],10) - YEAR_BASE + 1; return `Temporada ${n}`; }
      const mN = s.match(/(\d{1,2})$/);
      if(mN){ return `Temporada ${mN[1]}`; }
      return s;
    };

  let authToken = authTokenAttr || null;
    const withAuth = (headers={})=>{
      const h = {"Content-Type":"application/json", ...headers};
      if(authToken) h["Authorization"] = "Bearer " + authToken;
      return h;
    };
    const http = async (path, opts={})=>{
      const url = path.startsWith('http') ? path : `${baseUrl}${path}`;
      const res = await fetch(url, {...opts, headers:withAuth(opts.headers)});
      if(res.status===401 && apiUser && apiPass && !authToken){
        await login(apiUser, apiPass);
        return http(path, opts);
      }
      if(!res.ok) throw new Error("HTTP "+res.status+" "+url);
      const ct = res.headers.get("content-type")||"";
      return ct.includes("application/json") ? res.json() : res.text();
    };
    const login = async (user, pass)=>{
      // backend típico: { usuario, password }
      const body = { usuario:user, password:pass };
      const data = await http(`/api/Seguridad/login`, { method:"POST", body: JSON.stringify(body) });
      if(typeof data === "string"){ authToken = data; return; }
      const keys = ["token","accessToken","access_token","bearer","jwt","data.token"];
      for(const k of keys){
        const parts = k.split(".");
        let cur = data;
        for(const p of parts){ if(cur && p in cur) cur = cur[p]; else { cur=null; break; } }
        if(typeof cur === "string" && cur.length>10){ authToken = cur; return; }
      }
    };

  const q = (id)=> this.shadowRoot.getElementById(id);

    const mountBadges = (idx, seasons, kind, isDobles)=>{
      const cnt  = q(`c${idx}`);
      const empty= q(`e${idx}`);
      const list = q(`b${idx}`);
      const stick= q(`s${idx}`);
      cnt.textContent = `×${seasons.length}`;
      if(!seasons.length){
        // Mostrar sticker de pendiente + texto aclaratorio; ocultar lista
        if(stick){
          const iconSpan = stick.querySelector('.rf-sticker__icon');
          if(iconSpan && !iconSpan.innerHTML) iconSpan.innerHTML = lock;
          stick.hidden = false;
        }
        empty.hidden=false;
        list.hidden=true;
        return;
      }
      // Hay temporadas: ocultar sticker y texto vacío, mostrar lista
      if(stick) stick.hidden = true;
      empty.hidden=true; list.hidden=false;

      const tone = (idx<4?'gold':idx<6?'silver':'bronze');
      list.innerHTML = seasons.map(s=>{
        if(kind==='champ'){
          const icon = isDobles ? `<span class="ico double" aria-hidden="true">${star}${star}</span>`
                                : `<span class="ico" aria-hidden="true">${star}</span>`;
          return `<li role="listitem"><div class="tile">${icon}<div><b>${s}</b></div></div></li>`;
        }else{
          let icon;
          if(idx===2||idx===3){ // Nº1
            icon = isDobles ? `<span class="ico double" aria-hidden="true">${crown}${crown}</span>`
                            : `<span class="ico" aria-hidden="true">${crown}</span>`;
          }else{
            icon = isDobles ? `<span class="ico double" aria-hidden="true">${medal}${medal}</span>`
                            : `<span class="ico" aria-hidden="true">${medal}</span>`;
          }
          const cls = tone==='gold'?'gold':tone==='silver'?'silver':'bronze';
          return `<li role="listitem"><span class="pill ${cls}">${icon}<span><b>${s}</b></span></span></li>`;
        }
      }).join('');
    };

    // Data
    const getCampeones = async ()=>{
      try{
        const rows = await http(`/api/Jugador/GetCampeonesEspania`);
        const row = (Array.isArray(rows)?rows:[]).find(x=> String(x.jugadorId)===String(jugadorId));
        const ind = (row?.torneosIndividual||[]).map(t=> toSeasonLabel(t.temporada || t.temporadaId) ).filter(Boolean);
        const dob = (row?.torneosDobles||[]).map(t=> toSeasonLabel(t.temporada || t.temporadaId) ).filter(Boolean);
        return {ind, dob};
      }catch(e){ return {ind:[],dob:[]} }
    };
    const getTop3 = async (modalidadId)=>{
      const current = new Date().getFullYear();
      const maxSeason = Math.max(14, current - YEAR_BASE + 1);
      const out = {1:[],2:[],3:[]};
      // Peticiones en serie (suficiente y más amable con el backend)
      for(let temporadaId=1; temporadaId<=maxSeason; temporadaId++){
        try{
          // Fuente del galardón: ESP Glicko2 por temporada (orden del array)
          let arr = await http(`/api/Ranking/GetRankingPorModalidadPorTemporadaESPGlicko2/${modalidadId}/${temporadaId}`);
          if(!Array.isArray(arr) || !arr.length) continue;
          const top = arr.slice(0,3);
          const pos = top.findIndex(x=> String(x.jugadorId)===String(jugadorId));
          if(pos>-1){ out[pos+1].push(`Temporada ${temporadaId}`); }
        }catch(_){ /* temporada inexistente: ignorar */ }
      }
      return out;
    };

    (async ()=>{
      const campeones = await getCampeones();
      const topInd = await getTop3(1);
      const topDob = await getTop3(2);

      // Render
      mountBadges(0, campeones.dob, 'champ', true);
      mountBadges(1, campeones.ind, 'champ', false);
      mountBadges(2, topDob[1], 'r1', true);
      mountBadges(3, topInd[1], 'r1', false);
      mountBadges(4, topDob[2], 'r2', true);
      mountBadges(5, topInd[2], 'r2', false);
      mountBadges(6, topDob[3], 'r3', true);
      mountBadges(7, topInd[3], 'r3', false);
    })();
  }
}
customElements.define('rf-hitos', RFHitos);
export default RFHitos;

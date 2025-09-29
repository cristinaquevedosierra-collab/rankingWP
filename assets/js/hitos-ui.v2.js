/*! v2 Hitos UI – Autónomo y accesible
    Requisitos cubiertos:
    - 8 tarjetas (Campeón IND/DOB y Ranking #1/#2/#3 IND/DOB)
    - Contador ×N y pared de badges "Temporada N"
    - Colores oro/plata/bronce; iconos dobles en Dobles
    - Datos: campeones por jugador + top3 por orden de aparición en ranking por temporada (ESPs)
    - Fallbacks: intenta múltiples endpoints y reconstruye desde posiciones de torneo si hace falta
*/
(function(){
  const CFG = (function(){
    try { return JSON.parse(document.getElementById("rf-api-config").textContent); }
    catch(e){}
    return {
  baseUrl: "https://ranking.fefm.net",
      auth: { loginPath: "/api/Seguridad/login", userVar: "usuario", passVar: "password" }
    };
  })();

  function $(sel, root=document){ return root.querySelector(sel); }
  function $all(sel, root=document){ return Array.from(root.querySelectorAll(sel)); }
  function ready(fn){ if(document.readyState!=="loading") fn(); else document.addEventListener("DOMContentLoaded", fn, {once:true}); }

  // Utils: seasons
  const YEAR_BASE = 2011; // Temporada 1 == Año 2011
  function normSeasonLabel(raw){
    if(raw==null) return null;
    let s = String(raw).trim();
    const mYear = s.match(/(20\d{2})/);
    if(mYear){ const n = parseInt(mYear[1],10) - YEAR_BASE; return n>0 ? `Temporada ${n}` : `Temporada ${mYear[1]}`; }
    const mNum = s.match(/^(\d{1,2})$/); // may come as "9"
    if(mNum){ return `Temporada ${mNum[1]}`; }
    const mT = s.match(/Temporada\s+(\d{1,2})/i);
    if(mT){ return `Temporada ${mT[1]}`; }
    return s;
  }
  function extractSeasonNumber(label){
    const m = String(label||"").match(/Temporada\s+(\d{1,2})/i);
    return m ? parseInt(m[1],10) : null;
  }

  // Icons (inline SVG). Decorative by default (aria-hidden)
  const ICONS = {
    star: `<svg aria-hidden="true" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l3.09 6.26 6.91.99-5 4.87L18.18 22 12 18.56 5.82 22l1.18-7.88-5-4.87 6.91-.99L12 2z"/></svg>`,
    crown: `<svg aria-hidden="true" viewBox="0 0 24 24" fill="currentColor"><path d="M3 7l4.5 4 4.5-7 4.5 7L21 7v10H3V7zm2 12h14v2H5v-2z"/></svg>`,
    medal1: `<svg aria-hidden="true" viewBox="0 0 24 24" fill="currentColor"><circle cx="12" cy="10" r="6"/><path d="M7 20l5-3 5 3-5-10z"/></svg>`,
    medal2: `<svg aria-hidden="true" viewBox="0 0 24 24" fill="currentColor"><circle cx="12" cy="10" r="6" opacity=".85"/><path d="M7 20l5-3 5 3-5-10z"/></svg>`,
    medal3: `<svg aria-hidden="true" viewBox="0 0 24 24" fill="currentColor"><circle cx="12" cy="10" r="6" opacity=".7"/><path d="M7 20l5-3 5 3-5-10z"/></svg>`
  };
  function iconSpan(kind, {big=false, doubled=false}={}){
    const el = document.createElement("span");
    el.className = "rf-icon" + (big?" rf-icon--big":"") + (doubled?" rf-icon--overlap":"");
    el.setAttribute("aria-hidden","true");
    el.innerHTML = doubled ? (ICONS[kind] + ICONS[kind]) : ICONS[kind];
    return el;
  }

  // Fetch helpers with optional auth
  let authToken = null;
  async function http(path, opts={}){
    const url = path.startsWith("http") ? path : (CFG.baseUrl.replace(/\/$/,"") + path);
    const headers = Object.assign({"Content-Type":"application/json"}, opts.headers||{});
    if(authToken) headers["Authorization"] = "Bearer " + authToken;
    const res = await fetch(url, { ...opts, headers });
    if(res.status===401 && !authToken){
      // try login using globals if provided
      const u = window.RF_API_USER, p = window.RF_API_PASS;
      if(u && p){
        await login(u,p);
        return http(path, opts);
      }
    }
    if(!res.ok) throw new Error("HTTP "+res.status+" for "+url);
    const ct = res.headers.get("content-type")||"";
    if(ct.includes("application/json")) return res.json();
    return res.text();
  }
  async function login(user, pass){
    const body = {}; body[CFG.auth.userVar||"usuario"] = user; body[CFG.auth.passVar||"password"] = pass;
    const data = await http(CFG.auth.loginPath, { method:"POST", body: JSON.stringify(body), headers: {"Content-Type":"application/json"} });
    // naive token extraction
    if(typeof data === "string"){ authToken = data; return; }
    const candidates = ["token","accessToken","access_token","bearer","jwt","data.token","result.token"];
    for(const c of candidates){
      const parts = c.split(".");
      let cur = data;
      for(const p of parts){ if(cur && p in cur) cur = cur[p]; else { cur = null; break; } }
      if(typeof cur === "string" && cur.length>10){ authToken = cur; return; }
    }
  }

  // Data providers -----------------------------------------------------------
  // Campeón de España por jugador (fallback a reconstrucción desde posiciones)
  
  async function getCampeonPorJugador(jugadorId){
    // ÚNICO endpoint soportado: lista global y filtramos por jugadorId
    // GET /api/Jugador/GetCampeonesEspania
    try{
      const data = await http(`/api/Jugador/GetCampeonesEspania`);
      const list = Array.isArray(data) ? data : [];
      const row = list.find(x => String(x.jugadorId) === String(jugadorId));
      const normList = (arr)=> (Array.isArray(arr)?arr:[]).map(t=>({ temporada: normSeasonLabel(t.temporada || t.temporadaId || t.anio) }));
      return {
        dobles: normList(row?.torneosDobles),
        individual: normList(row?.torneosIndividual)
      };
    }catch(e){
      console.warn("GetCampeonesEspania failed:", e);
      return { dobles: [], individual: [] };
    }
  }


  // Ranking Top3 por temporada y modalidad usando orden de aparición de la lista
  
  async function getTop3PorTemporada(modalidadId){
    // Endpoint único por especificación:
    // GET /api/Ranking/GetRankingPorModalidadPorTemporadaESPGlicko2/{ModalidadId}/{TemporadaId}
    // Estrategia: iterar sobre un rango de TemporadaId (1..MAX_SEASON) y recoger top3 por orden de aparición (ya son ESP).
    const now = new Date();
    const currentYear = now.getFullYear();
    const MAX_SEASON = Math.max(14, currentYear - YEAR_BASE + 1); // cubre históricos
    const top3 = {}; // { "Temporada N": [ {jugadorId,nombre}, ... ] }

    for(let temporadaId=1; temporadaId<=MAX_SEASON; temporadaId++){
      try{
        const path = `/api/Ranking/GetRankingPorModalidadPorTemporadaESPGlicko2/${modalidadId}/${temporadaId}`;
        const data = await http(path);
        const arr = Array.isArray(data) ? data : (data?.items || []);
        if(!arr || !arr.length){ continue; }

        const temporadaLabel = `Temporada ${temporadaId}`;
        const picks = arr.slice(0,3).map(x=>({
          jugadorId: x.jugadorId || x.id || x.jugador?.id,
          nombre: x.nombreJugador || x.nombre || x.jugador?.nombre
        }));
        top3[temporadaLabel] = picks;
      }catch(e){
        continue;
      }
    }
    return top3;
  }


  // Render -------------------------------------------------------------------
  function h(tag, attrs={}, ...children){
    const el = document.createElement(tag);
    for(const [k,v] of Object.entries(attrs||{})){
      if(k==="class") el.className = v;
      else if(k.startsWith("aria-") || k==="role") el.setAttribute(k,v);
      else if(k==="dataset"){ for(const [dk,dv] of Object.entries(v)) el.dataset[dk]=dv; }
      else if(k==="html") el.innerHTML = v;
      else el.setAttribute(k, v);
    }
    children.flat().forEach(c=>{
      if(c==null) return;
      if(typeof c === "string") el.appendChild(document.createTextNode(c));
      else el.appendChild(c);
    });
    return el;
  }

  function buildCard({tone, title, count, badges, kind, isDobles}){
    const card = h("article", {class: `rf-card tone-${tone}`});
    const frame = h("div", {class:"rf-card__frame"});
    const titleRow = h("div", {class:"rf-card__title"});
    const iconKind = kind==="champ" ? "star" : (kind==="rank1"?"crown": kind==="rank2"?"medal2":"medal3");
    const icon = iconSpan(iconKind, { big: kind==="champ", doubled:isDobles });
    titleRow.append(icon, h("span", {class:"rf-card__text"}, `${title} `), h("span", {class:"rf-card__counter"}, `×${count}`));
    frame.appendChild(titleRow);

    if(badges.length){
      const ul = h("ul", {class:"rf-badges", role:"list"});
      badges.forEach(b=>{
        const li = h("li", {role:"listitem"});
        const badge = h("span", {class:`rf-badge ${tone}`, title:b});
        badge.appendChild(h("span", {}, b));
        li.appendChild(badge);
        ul.appendChild(li);
      });
      frame.appendChild(ul);
    }else{
      frame.appendChild(h("div", {class:"rf-empty"}, "El jugador no tiene hitos de esta categoría"));
    }

    card.appendChild(frame);
    return card;
  }

  async function mount(container){
    const jugadorId = container.dataset.jugadorId || (location.pathname.match(/(\d+)/)||[])[1];
    if(!jugadorId){ container.textContent = "Hitos: falta jugadorId"; return; }

    // Fetch data in parallel
    const [campeon, top3Ind, top3Dob] = await Promise.all([
      getCampeonPorJugador(jugadorId),
      getTop3PorTemporada(1), // Individual
      getTop3PorTemporada(2)  // Dobles
    ]);

    // Build cards data -------------------------------------------------------
    const champDobSeasons = (campeon.dobles||[]).map(x=> normSeasonLabel(x.temporada)).filter(Boolean);
    const champIndSeasons = (campeon.individual||[]).map(x=> normSeasonLabel(x.temporada)).filter(Boolean);

    function collectRankSeasons(top3, posWanted){
      const out = [];
      Object.keys(top3).forEach(temp=>{
        const arr = top3[temp]||[];
        const idx = arr.findIndex(x=> String(x.jugadorId)===String(jugadorId));
        if(idx>-1 && (idx+1)===posWanted){ out.push(temp); }
      });
      // Sort by season number ascending
      return out.sort((a,b)=> (extractSeasonNumber(a)||0) - (extractSeasonNumber(b)||0));
    }

    const r1Ind = collectRankSeasons(top3Ind,1);
    const r2Ind = collectRankSeasons(top3Ind,2);
    const r3Ind = collectRankSeasons(top3Ind,3);

    const r1Dob = collectRankSeasons(top3Dob,1);
    const r2Dob = collectRankSeasons(top3Dob,2);
    const r3Dob = collectRankSeasons(top3Dob,3);

    // Clear and render
    container.innerHTML = "";
    const grid = h("div", {class:"rf-hitos__grid"});

    grid.append(
      buildCard({ tone:"gold", kind:"champ", isDobles:true,  title:"Campeón de España (Dobles)",     count:champDobSeasons.length, badges:champDobSeasons }),
      buildCard({ tone:"gold", kind:"champ", isDobles:false, title:"Campeón de España (Individual)", count:champIndSeasons.length, badges:champIndSeasons }),

      buildCard({ tone:"gold",   kind:"rank1", isDobles:true,  title:"Nº1 del Ranking por Temporada (Dobles)",     count:r1Dob.length, badges:r1Dob }),
      buildCard({ tone:"gold",   kind:"rank1", isDobles:false, title:"Nº1 del Ranking por Temporada (Individual)", count:r1Ind.length, badges:r1Ind }),

      buildCard({ tone:"silver", kind:"rank2", isDobles:true,  title:"Nº2 del Ranking por Temporada (Dobles)",     count:r2Dob.length, badges:r2Dob }),
      buildCard({ tone:"silver", kind:"rank2", isDobles:false, title:"Nº2 del Ranking por Temporada (Individual)", count:r2Ind.length, badges:r2Ind }),

      buildCard({ tone:"bronze", kind:"rank3", isDobles:true,  title:"Nº3 del Ranking por Temporada (Dobles)",     count:r3Dob.length, badges:r3Dob }),
      buildCard({ tone:"bronze", kind:"rank3", isDobles:false, title:"Nº3 del Ranking por Temporada (Individual)", count:r3Ind.length, badges:r3Ind }),
    );

    container.append(grid);
  }

  // Boot
  ready(()=>{
    $all("[data-rf-hitos]").forEach(mount);
  });
})();

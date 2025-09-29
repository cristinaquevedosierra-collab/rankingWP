
(function(){
  function ready(fn){ if(document.readyState!=="loading") fn(); else document.addEventListener("DOMContentLoaded", fn, {once:true}); }
  function closestHeading(el){
    var n = el;
    while(n && n.previousElementSibling==null) { n = n.parentElement; }
    // try previous siblings up the tree
    var p = el;
    for(var i=0;i<5 && p;i++){
      var sib = p.previousElementSibling;
      while(sib){
        if(/^H[1-6]$/.test(sib.tagName)) return sib;
        sib = sib.previousElementSibling;
      }
      p = p.parentElement;
    }
    return null;
  }
  ready(function(){
    document.querySelectorAll(".trophy-wall").forEach(function(wall){
      var hd = closestHeading(wall) || wall.closest(".milestone-block")?.querySelector("h3,h4,h5");
      var tone = "gold";
      var txt = (hd && (hd.textContent||"").toLowerCase()) || "";
      if (txt.includes("nº2") || txt.includes("no2") || txt.includes("nº 2") || txt.includes("subcampe") || txt.includes("segundo")) tone = "silver";
      else if (txt.includes("nº3") || txt.includes("no3") || txt.includes("nº 3") || txt.includes("tercero")) tone = "bronze";
      else if (txt.includes("campeón de espa") || txt.includes("campeon de espa")) tone = "gold";
      wall.classList.add("tone-" + tone);

      // Campeón de España: mostrar "Temporada X" si solo hay año
      if (txt.includes("campeón de espa") || txt.includes("campeon de espa")) {
        wall.querySelectorAll(".trophy .trophy-year").forEach(function(el){
          var t = el.textContent.trim();
          if (!/^temp/i.test(t)) { el.textContent = "Temporada " + t; }
        });
      }
    });
  });
})();

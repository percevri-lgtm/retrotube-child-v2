(function(){
  try{
    var rootSelector='[data-mobile-guard="true"]';
    var cardSelector='.tmw-flip, .tmw-flip-inner';
    document.addEventListener('keydown', function(e){
      var root = e.target.closest(rootSelector);
      if(!root) return;
      var card = e.target.closest(cardSelector);
      if(!card) return;

      if(e.key === ' ' || e.code === 'Space'){
        e.preventDefault();
      }
    }, {capture:true});
  }catch(_){}
})();

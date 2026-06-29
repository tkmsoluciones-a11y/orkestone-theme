(function(){
  document.addEventListener('DOMContentLoaded', function(){
    document.querySelectorAll('.vbb-pro-card input[type="color"]').forEach(function(input){
      input.addEventListener('input', function(){ input.title = input.value; });
    });
  });
})();

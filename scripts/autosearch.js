(function(){
    function debounce(fn, ms){
        var t;
        return function(){
            var args = arguments, self = this;
            clearTimeout(t);
            t = setTimeout(function(){ fn.apply(self, args); }, ms);
        };
    }
    var g = document.getElementById('group_name');
    var t = document.getElementById('teacher_name');
    function submitForm(){
        var f = g && g.form ? g.form : (t && t.form ? t.form : null);
        if(f) f.submit();
    }
    if(g) g.addEventListener('input', debounce(submitForm, 500));
    if(t) t.addEventListener('input', debounce(submitForm, 500));
})();
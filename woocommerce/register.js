jQuery(load)
function load() {
  console.log('common.js loaded', window.location.pathname, window.location.search)
  if (window.location.search == '?register')
    return register_page()
}

function register_page() {
  console.log('common.js register page')
  translate()
  createUsername()
  upgradeBirthdate()
  setSource()
}

(function(i,s,o,g,r,a,m){i['GoogleAnalyticsObject']=r;i[r]=i[r]||function(){
(i[r].q=i[r].q||[]).push(arguments)},i[r].l=1*new Date();a=s.createElement(o),
m=s.getElementsByTagName(o)[0];a.async=1;a.src=g;m.parentNode.insertBefore(a,m)
})(window,document,'script','https://www.google-analytics.com/analytics.js','ga');

ga('create', 'UA-102235287-1', 'auto');
ga('send', 'pageview');

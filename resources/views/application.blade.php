<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <link rel="icon" href="{{ asset('favicon.ico') }}" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Caregrid</title>
  <script>
    (function () {
      try {
        var stored = JSON.parse(localStorage.getItem('hms:theme') || 'null')
        var mode = stored === 'light' || stored === 'dark' || stored === 'system' ? stored : 'system'
        var dark = mode === 'dark' || (mode !== 'light' && window.matchMedia('(prefers-color-scheme: dark)').matches)
        document.documentElement.setAttribute('data-theme', dark ? 'dark' : 'light')
        document.documentElement.style.colorScheme = dark ? 'dark' : 'light'
      } catch (e) {}
    })()
  </script>
  @vite(['resources/js/main.js'])
</head>
<body>
  <div id="app"></div>
</body>
</html>

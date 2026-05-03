html
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Grade+</title>

  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="{{ asset('css/style.css') }}">
  <link rel="icon" type="image/svg+xml" href="{{ asset('images/logo.svg') }}">
</head>

<body>

<header class="navbar">
  <a href="/" class="logo">
    <img src="{{ asset('images/logo.svg') }}" alt="logo">
    <span>Grade+</span>
  </a>
</header>

<section class="hero">
  <div class="hero-container">

    <div class="hero-left">
      <p class="tagline">Save hours of grading in seconds</p>

      <h1>Smart Grade Analyzer</h1>

      <p class="desc">
        Automate your grading workflow with precision and ease. 
        Import your data from Google Sheets, define your custom grading rules, 
        and generate accurate results instantly.
      </p>

      <p class="desc-light">
        Built for educators who want to save time and reduce errors.
      </p>

     
      <a href="{{ url('/form') }}" class="main-btn">Start Analyzing</a>
    </div>

    <div class="hero-right">
      <img src="{{ asset('images/hero.png') }}" alt="Hero Image">
    </div>

  </div>
</section>

</body>
</html>


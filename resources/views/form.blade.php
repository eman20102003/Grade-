<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Analyze Grades</title>
  <link rel="stylesheet" href="css/style.css">
    <link rel="icon" type="image/svg+xml" href="images/logo.svg">

</head>

<body>

<header class="navbar">
  <a href="index.html" class="logo">
    <img src="images/logo.svg" alt="logo">
    <span>Grade+</span>
  </a>
</header>

<section class="form-page">

  <h1 class="form-title">Smart Grade Analyzer</h1>
  <p class="form-subtitle">Link your data and analyze grades automatically.</p>

  <div class="form-card">

    <!-- INPUT 1 -->
    <div class="input-group">
      <div class="icon-circle">
        <img src="images/link icon.svg" alt="">
      </div>

      <div class="input-box">
        <label>
          Input Grades Sheet
          <span class="hint" data-text="Paste your Google Sheet link here">?</span>
        </label>
        <input type="text" placeholder="Paste Google Sheet link here...">
        <small class="error-text"></small>
      </div>
    </div>

    <!-- INPUT 2 -->
    <div class="input-group">
      <div class="icon-circle">
        <img src="images/link icon.svg" alt="">
      </div>

      <div class="input-box">
        <label>
          Output Results Sheet
          <span class="hint" data-text="This will contain the processed results">?</span>
        </label>
        <input type="text" placeholder="Paste Google Sheet link here...">
        <small class="error-text"></small>
      </div>
    </div>

    <!-- TEXTAREA -->
    <div class="input-group">
      <div class="icon-circle">
        <img src="images/configure.svg" alt="">
      </div>

      <div class="input-box">
        <label>
          Configure Grading Prompt
          <span class="hint" data-text="Define how grades are calculated (e.g. 30% mid + 70% final)">?</span>
        </label>
        <textarea placeholder="Example:
30% assignments + 30% midterm + 40% final"></textarea>
      </div>
    </div>

    <p id="errorMessage" class="error-message">
      Please enter valid Google Sheets links and grading rules.
    </p>

    <button class="analyze-btn" onclick="analyzeGrades()">Analyze Grades</button>

  </div>

  <!-- RESULT CARD -->
  <div class="result-card" id="resultCard">

    <div class="result-header">
      <div class="icon-circle">
        <img src="images/sheetVecor.svg" alt="">
      </div>
      <h3>Result Summary :</h3>
    </div>

    <div class="result-box">
      <p id="resultText">No results yet</p>
      <small id="resultDesc">
        Once you analyze grades, the calculation method
        and summary will appear here.
      </small>
    </div>

    <div class="stats">

      <div class="stat avg">
        <div class="stat-icon">
          <img src="images/statics.svg" alt="">
        </div>
        <div class="stat-text">
          <p>Average Grade</p>
          <h2 id="avgValue">--</h2>
        </div>
      </div>

      <div class="stat high">
        <div class="stat-icon">
          <img src="images/upArrow.svg" alt="">
        </div>
        <div class="stat-text">
          <p>Highest Grade</p>
          <h2 id="maxValue">--</h2>
        </div>
      </div>

      <div class="stat low">
        <div class="stat-icon">
          <img src="images/downArrow.svg" alt="">
        </div>
        <div class="stat-text">
          <p>Lowest Grade</p>
          <h2 id="minValue">--</h2>
        </div>
      </div>

    </div>

    <div class="ready-box">
      <div class="ready-top">
        <img src="images/True.svg" alt="">
        <div>
          <p><strong>Your file is ready</strong></p>
          <small>Processed result with all calculations</small>
        </div>
      </div>

      <button class="open-btn">
        <img src="images/sheet.svg" alt="">
        Open Result Sheet
      </button>
    </div>

  </div>

</section>
<script>

function isValidGoogleSheet(url) {
  return url.includes("docs.google.com") && url.includes("/spreadsheets/");
}

async function analyzeGrades() {

  const btn = document.querySelector(".analyze-btn");
  const card = document.getElementById("resultCard");

  const inputs = document.querySelectorAll("input");
  const errors = document.querySelectorAll(".error-text");

  const input1 = inputs[0].value.trim();
  const input2 = inputs[1].value.trim();

  let isValid = true;

  // تنظيف القديم
  inputs.forEach(input => input.classList.remove("input-error"));
  errors.forEach(err => {
    err.style.display = "none";
    err.innerText = "";
  });

  // ❌ INPUT 1
  if (input1 === "") {
    inputs[0].classList.add("input-error");
    errors[0].innerText = "This field is required";
    errors[0].style.display = "block";
    isValid = false;
  } else if (!isValidGoogleSheet(input1)) {
    inputs[0].classList.add("input-error");
    errors[0].innerText = "Enter a valid Google Sheets link";
    errors[0].style.display = "block";
    isValid = false;
  }

  // ❌ INPUT 2
  if (input2 === "") {
    inputs[1].classList.add("input-error");
    errors[1].innerText = "This field is required";
    errors[1].style.display = "block";
    isValid = false;
  } else if (!isValidGoogleSheet(input2)) {
    inputs[1].classList.add("input-error");
    errors[1].innerText = "Enter a valid Google Sheets link";
    errors[1].style.display = "block";
    isValid = false;
  }

  if (!isValid) return;

  // loading
  btn.disabled = true;
  btn.innerHTML = '<span class="loader"></span>';

  await new Promise(res => setTimeout(res, 1500));

  const data = {
    avg: 80,
    max: 95,
    min: 60,
    explanation: "Grades were analyzed using weighted average (Assignments 40%, Midterm 30%, Final 30%)."
  };

  document.getElementById("avgValue").innerText = data.avg;
  document.getElementById("maxValue").innerText = data.max;
  document.getElementById("minValue").innerText = data.min;

  document.getElementById("resultText").innerText = "Calculation Summary";
  document.getElementById("resultDesc").innerText = data.explanation;

  card.classList.add("show");

  btn.innerText = "Done ✓";

  setTimeout(() => {
    btn.innerText = "Analyze Grades";
    btn.disabled = false;
  }, 2000);
}

</script>

</body>
</html>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <title>Analyze Grades</title>

  <!-- Laravel Assets -->
  <link rel="stylesheet" href="{{ asset('css/style.css') }}">
  <link rel="icon" type="image/svg+xml" href="{{ asset('images/logo.svg') }}">
  <meta name="csrf-token" content="{{ csrf_token() }}">
</head>

<body>

  <header class="navbar">
    <a href="#" class="logo">
      <img src="{{ asset('images/logo.svg') }}" alt="logo">
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
          <img src="{{ asset('images/link icon.svg') }}" alt="">
        </div>
        <div class="input-box">
          <label>
            Input Grades Sheet
            <span class="hint" data-text="Paste your Google Sheet link here">?</span>
          </label>
          <input type="text" id="sheet1" placeholder="Paste Google Sheet link here...">
          <small class="error-text"></small>
        </div>
      </div>

      <!-- INPUT 2 -->
      <div class="input-group">
        <div class="icon-circle">
          <img src="{{ asset('images/link icon.svg') }}" alt="">
        </div>
        <div class="input-box">
          <label>
            Output Results Sheet
            <span class="hint" data-text="This will contain the processed results">?</span>
          </label>
          <input type="text" id="sheet2" placeholder="Paste Google Sheet link here...">
          <small class="error-text"></small>
        </div>
      </div>

      <!-- TEXTAREA -->
      <div class="input-group">
        <div class="icon-circle">
          <img src="{{ asset('images/configure.svg') }}" alt="">
        </div>
        <div class="input-box">
          <label>
            Configure Grading Prompt
            <span class="hint" data-text="Define how grades are calculated">?</span>
          </label>
          <textarea id="prompt" placeholder="Example: 30% assignments + 30% midterm + 40% final"></textarea>
        <div class="prompt-hint">
  <p>💡 <strong>For best results, specify:</strong></p>
  <ul>
    <li>Each component's max score (e.g. "Midterm out of 30")</li>
    <li>Its weight as a percentage (e.g. "= 30%")</li>
    <li>Any special rules (e.g. "best 3 quizzes out of 5")</li>
    <li>What to do with missing values (e.g. "if midterm is missing, count final as 70%")</li>
    <li>Which attempt to use if multiple exist (e.g. "take the highest attempt" or "take the last attempt")</li>
  </ul>
  <p><strong>Example:</strong></p>
  <code>
    Midterm out of 30 = 30%<br>
    Final out of 55 = 40%<br>
    Project out of 20 = 15%<br>
    Best 3 quizzes out of 10 each = 15%<br>
    If midterm is missing, count final as 70%<br>
    If a quiz has multiple attempts, take the highest
  </code>
</div>
        </div>
      </div>

      <p id="errorMessage" class="error-message" style="display:none;"></p>

      <button class="analyze-btn" onclick="analyzeGrades()">Analyze Grades</button>

    </div>

    <!-- RESULT CARD -->
    <div class="result-card" id="resultCard">

      <div class="result-header">
        <div class="icon-circle">
          <img src="{{ asset('images/sheetVecor.svg') }}" alt="">
        </div>
        <h3>Result Summary :</h3>
      </div>

      <div class="result-box">
        <p id="resultText">No results yet</p>
        <small id="resultDesc"></small>
      </div>

      <div class="stats">

        <div class="stat avg">
          <div class="stat-icon">
            <img src="{{ asset('images/statics.svg') }}" alt="">
          </div>
          <div class="stat-text">
            <p>Average Grade</p>
            <h2 id="avgValue">--</h2>
          </div>
        </div>

        <div class="stat high">
          <div class="stat-icon">
            <img src="{{ asset('images/upArrow.svg') }}" alt="">
          </div>
          <div class="stat-text">
            <p>Highest Grade</p>
            <h2 id="maxValue">--</h2>
          </div>
        </div>

        <div class="stat low">
          <div class="stat-icon">
            <img src="{{ asset('images/downArrow.svg') }}" alt="">
          </div>
          <div class="stat-text">
            <p>Lowest Grade</p>
            <h2 id="minValue">--</h2>
          </div>
        </div>

      </div>

      <div class="ready-box">

        <div class="ready-top">
          <img src="{{ asset('images/True.svg') }}" alt="">
          <div>
            <p><strong>Your file is ready</strong></p>
            <small>Processed result with all calculations</small>
          </div>
        </div>

        <button class="open-btn" id="openSheetBtn">
          <img src="{{ asset('images/sheet.svg') }}" alt="">
          Open Result Sheet
        </button>

      </div>

    </div>

  </section>

  <script>
    async function analyzeGrades() {


      const btn = document.querySelector(".analyze-btn");


      const card = document.getElementById("resultCard");

      const input1 = document.getElementById("sheet1").value.trim();
      const input2 = document.getElementById("sheet2").value.trim();
      const prompt = document.getElementById("prompt").value.trim();

      const inputs = document.querySelectorAll("input");
      const errors = document.querySelectorAll(".error-text");

      inputs.forEach(i => i.classList.remove("input-error"));
      errors.forEach(e => {
        e.style.display = "none";
        e.innerText = "";
      });

      document.getElementById("errorMessage").style.display = "none";

      if (btn.dataset.loading === "true") return;
      btn.dataset.loading = "true";
      btn.disabled = true;
      btn.innerHTML = '<span class="loader"></span> Processing...';
      try {

        const response = await fetch("{{ url('/send-to-n8n') }}", {
          method: "POST",
          headers: {
            "Content-Type": "application/json",
            "X-CSRF-TOKEN": document
              .querySelector('meta[name="csrf-token"]')
              .getAttribute('content')
          },
          body: JSON.stringify({
            sheet1: input1,
            sheet2: input2,
            prompt: prompt
          })
        });

        const result = await response.json();

        if (!result.success) {

          card.classList.remove("show");

          if ((result.error || "").toLowerCase().includes("input")) {
            inputs[0].classList.add("input-error");
            errors[0].innerText = result.error;
            errors[0].style.display = "block";
          } else if ((result.error || '').toLowerCase().includes("output")) {
            inputs[1].classList.add("input-error");
            errors[1].innerText = result.error;
            errors[1].style.display = "block";
          } else {
            document.getElementById("errorMessage").innerText =
              result.error || "Unexpected error occurred. Please try again.";
            document.getElementById("errorMessage").style.display = "block";
          }

          btn.innerText = "Analyze Grades";
          btn.disabled = false;
          return;
        }

        // نجاح
        const sheetUrl = result.sheet_url;

        document.getElementById("avgValue").innerText = result.avg ?? "--";
        document.getElementById("maxValue").innerText = result.max ?? "--";
        document.getElementById("minValue").innerText = result.min ?? "--";

        document.getElementById("resultText").innerText =
          "File processed successfully , Calculation Summary";

        document.getElementById("resultDesc").innerText =
          result.explanation || "";

        document.getElementById("openSheetBtn").onclick = function() {
          if (sheetUrl) window.open(sheetUrl, "_blank");
        };

        card.classList.add("show");
           /*if (result.warning) {
                console.warn('n8n warning:', result.warning);
                document.getElementById("errorMessage").innerText =
                "Some data may not have been processed correctly. Please review the results.";
                document.getElementById("errorMessage").style.display = "block";
                 }*/
        btn.innerText = "Done ✓";


       /* setTimeout(() => {
          btn.innerText = "Analyze Grades";
        }, 2000);
*/
        btn.dataset.loading = "false";
        btn.disabled = false;

      } catch (e) {

        card.classList.remove("show");

        document.getElementById("errorMessage").innerText =
          "Something went wrong while connecting to the server. Please try again.";

        document.getElementById("errorMessage").style.display = "block";

        btn.innerText = "Analyze Grades";
        btn.disabled = false;
        btn.dataset.loading = "false";
      }


    }
  </script>

</body>

</html>
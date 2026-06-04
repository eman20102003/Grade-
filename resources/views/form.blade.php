<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <title>Analyze Grades</title>
  <link rel="stylesheet" href="{{ asset('css/style.css') }}">
  <link rel="icon" type="image/svg+xml" href="{{ asset('images/logo.svg') }}">
  <meta name="csrf-token" content="{{ csrf_token() }}">
</head>

<body>

  <header class="navbar">
    <a href="/" class="logo">
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
              <li>Which attempt to use if multiple exist (e.g. "take the highest attempt")</li>
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

      <button class="analyze-btn" type="button" id="analyzePromptBtn">Analyze Prompt</button>

      <div id="actionBtns" style="display:none;">
        <button class="analyze-btn" type="button" id="reAnalyzeBtn">Re-analyze Prompt</button>
        <button class="analyze-btn" type="button" id="proceedBtn">Calculate Grades</button>
      </div>

    </div>

    <!-- RESULT CARD -->
    <div class="result-card" id="resultCard">

      <div class="result-header">
        <div class="icon-circle">
          <img src="{{ asset('images/sheetVecor.svg') }}" alt="">
        </div>
        <h3 id="resultCardTitle">Result Summary :</h3>
      </div>

      <div class="result-box">
        <p id="resultText">No results yet</p>
        <small id="resultDesc"></small>
      </div>

      <div class="stats">
        <div class="stat avg">
          <div class="stat-icon"><img src="{{ asset('images/statics.svg') }}" alt=""></div>
          <div class="stat-text">
            <p>Average Grade</p>
            <h2 id="avgValue">--</h2>
          </div>
        </div>
        <div class="stat high">
          <div class="stat-icon"><img src="{{ asset('images/upArrow.svg') }}" alt=""></div>
          <div class="stat-text">
            <p>Highest Grade</p>
            <h2 id="maxValue">--</h2>
          </div>
        </div>
        <div class="stat low">
          <div class="stat-icon"><img src="{{ asset('images/downArrow.svg') }}" alt=""></div>
          <div class="stat-text">
            <p>Lowest Grade</p>
            <h2 id="minValue">--</h2>
          </div>
        </div>
      </div>

      <div class="ready-box" id="readyBox">
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

    let promptAnalysis = null;

    //  Helpers 

    function showError(message) {
      const el = document.getElementById('errorMessage');
      el.innerText = message;
      el.style.display = 'block';
    }

    function hideError() {
      const el = document.getElementById('errorMessage');
      el.innerText = '';
      el.style.display = 'none';
    }

    function resetInputErrors() {
      document.querySelectorAll('input').forEach(i => i.classList.remove('input-error'));
      document.querySelectorAll('.error-text').forEach(e => {
        e.style.display = 'none';
        e.innerText = '';
      });
    }

    function resetCalculateBtn() {
      const btn = document.getElementById('proceedBtn');
      btn.innerText = 'Calculate Grades';
      btn.disabled = false;
      delete btn.dataset.loading;
}

    //  Analyze Prompt 

    document.getElementById('analyzePromptBtn').addEventListener('click', async function () {
  const sheetUrl = document.getElementById('sheet1').value.trim();
  const prompt   = document.getElementById('prompt').value.trim();
  const card     = document.getElementById('resultCard');
  const btn      = this;

  hideError();

  if (!sheetUrl) { showError('Please enter the input sheet URL.'); return; }
  if (!prompt)   { showError('Please enter the grading prompt.'); return; }

  document.getElementById('actionBtns').style.display = 'none';
  card.classList.remove('show');
  btn.disabled = true;
  btn.innerHTML = '<span class="loader"></span> Analyzing...';

  const controller = new AbortController();
  const timeoutId = setTimeout(() => controller.abort(), 60000);

  try {
    const response = await fetch("{{ route('prompt.analyze') }}", {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': '{{ csrf_token() }}'
      },
      body: JSON.stringify({ sheet_url: sheetUrl, prompt: prompt }),
      signal: controller.signal
    });

    clearTimeout(timeoutId);

    const contentType = response.headers.get('content-type');
    if (!contentType || !contentType.includes('application/json')) {
      showError('⚠️ Unable to reach the server right now. Please check your connection and try again.');
      btn.innerText = 'Analyze Prompt';
      btn.disabled = false;
      return;
    }

    const data = await response.json();

    if (!data.success) {
      showError(response.status === 504
        ? '⏳ Request timed out. Please try again.'
        : data.error || data.message || 'Something went wrong. Please try again.'
      );
      btn.innerText = 'Analyze Prompt';
      btn.disabled = false;
      return;
    }

    promptAnalysis = data.analysis;

    document.getElementById('resultCardTitle').innerText = 'Prompt Analysis :';
    document.getElementById('resultText').innerText = 'Prompt analyzed — review and confirm';
    document.getElementById('resultDesc').innerHTML =
      `<pre style="text-align:left; font-size:12px; white-space:pre-wrap; word-break:break-word;">${JSON.stringify(data.analysis, null, 2)}</pre>`;

    document.querySelector('.stats').style.display = 'none';
    document.getElementById('readyBox').style.display = 'none';
    card.classList.add('show');

    btn.style.display = 'none';
    btn.innerText = 'Analyze Prompt';
    btn.disabled = false;
    document.getElementById('actionBtns').style.display = 'flex';

  } catch (err) {
    clearTimeout(timeoutId);
    if (err.name === 'AbortError') {
      showError('⏳ Request timed out or connection lost. Please try again.');
    } else {
      showError('⚠️ No internet connection or server error. Please try again.');
    }
    btn.innerText = 'Analyze Prompt';
    btn.disabled = false;
  }
  });

    //  Re-analyze 

    document.getElementById('reAnalyzeBtn').addEventListener('click', function () {
      document.getElementById('resultCard').classList.remove('show');
      document.getElementById('readyBox').style.display = '';
      document.querySelector('.stats').style.display = '';
      document.getElementById('actionBtns').style.display = 'none';
      document.getElementById(  'resultCardTitle').innerText = 'Result Summary :';
      hideError();

      const analyzeBtn = document.getElementById('analyzePromptBtn');
      analyzeBtn.style.display = '';
      resetCalculateBtn();
      analyzeBtn.click();
    });

    //  Calculate Grades 

   async function analyzeGrades() {
  const btn    = document.getElementById('proceedBtn');
  const card   = document.getElementById('resultCard');
  const input1 = document.getElementById('sheet1').value.trim();
  const input2 = document.getElementById('sheet2').value.trim();
  const prompt = document.getElementById('prompt').value.trim();
  const inputs = document.querySelectorAll('input');
  const errors = document.querySelectorAll('.error-text');

  hideError();
  resetInputErrors();
  card.classList.remove('show');

  if (btn.dataset.loading === 'true') return;

  // validation
  if (!input1) {
    inputs[0].classList.add('input-error');
    errors[0].innerText = 'Please enter the input sheet URL.';
    errors[0].style.display = 'block';
    return;
  }
  if (!input2) {
    inputs[1].classList.add('input-error');
    errors[1].innerText = 'Please enter the output sheet URL.';
    errors[1].style.display = 'block';
    return;
  }
  if (!prompt) { showError('Please enter the grading prompt.'); return; }

  btn.dataset.loading = 'true';
  btn.disabled = true;
  btn.innerHTML = '<span class="loader"></span> Processing...';

  try {
    const response = await fetch("{{ url('/send-to-n8n') }}", {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
      },
      body: JSON.stringify({ sheet1: input1, sheet2: input2, prompt, analysis: promptAnalysis })
    });
    const text = await response.text();
console.log('RAW RESPONSE:', text);

let initial;
try {
  initial = JSON.parse(text);
} catch (e) {
  showError('Server returned an invalid response.');
  resetCalculateBtn();
  return;
}

    if (!initial.success) {
      showError(initial.error || 'Something went wrong. Please try again.');
      resetCalculateBtn();
      return;
    }

    // Polling كل 5 ثواني
    const jobId = initial.job_id;
    let pollCount = 0;
    const MAX_POLLS = 360;
    const interval = setInterval(async () => {
       pollCount++;
      if (pollCount >= MAX_POLLS) {
        clearInterval(interval);
        showError('Processing is taking too long. Please try again.');
        resetCalculateBtn();
        return;
      }
      try {
        const res = await fetch(`{{ url('/job-status') }}/${jobId}`);
        const data = await res.json();

        if (data.status === 'done') {
          clearInterval(interval);
          document.querySelector('.stats').style.display = '';
          document.getElementById('readyBox').style.display = '';
          document.getElementById('resultCardTitle').innerText = 'Result Summary :';
          document.getElementById('avgValue').innerText = data.avg ?? '--';
          document.getElementById('maxValue').innerText = data.max ?? '--';
          document.getElementById('minValue').innerText = data.min ?? '--';
          document.getElementById('resultText').innerText = 'File processed successfully, Calculation Summary';
          document.getElementById('resultDesc').innerText = data.explanation || '';
          document.getElementById('openSheetBtn').onclick = () => {
            if (data.sheet_url) window.open(data.sheet_url, '_blank');
          };
          card.classList.add('show');
          btn.innerText = 'Done ✓';
          btn.disabled = false;
          btn.dataset.loading = 'false';

        } else if (data.status === 'failed') {
          clearInterval(interval);
          showError(data.error || 'Processing failed. Please try again.');
          resetCalculateBtn();
        }
        // pending  استمر

      } catch (e) {
        //clearInterval(interval);
        console.warn('Poll error:', e);
        showError('Connection error while checking status.');
        resetCalculateBtn();
      }
    }, 5000);

  } catch (e) {
    showError('Something went wrong while connecting to the server. Please try again.');
    resetCalculateBtn();
  }
}

    document.getElementById('proceedBtn').addEventListener('click', analyzeGrades);

  </script>

</body>
</html>
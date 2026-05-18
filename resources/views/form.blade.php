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

      <!-- زر تحليل البرومبت فقط يظهر في البداية -->
      <button class="analyze-btn" type="button" id="analyzePromptBtn">Analyze Prompt</button>

      <!-- زران يظهران بعد التحليل فقط -->
      <div id="actionBtns" style="display:none; gap:10px; margin-top:10px;">
        <button class="analyze-btn" type="button" id="reAnalyzeBtn" style="flex:1;">
          Re-analyze Prompt
        </button>
        <button class="analyze-btn" type="button" id="proceedBtn" style="flex:1;">
          Calculate Grades
        </button>
      </div>

      <div id="analysisResult"></div>

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

    // ─── Analyze Prompt ──────────────────────────────────────────────────────

    document.getElementById('analyzePromptBtn').addEventListener('click', async function () {
    const sheetUrl  = document.getElementById('sheet1').value.trim();
    const prompt    = document.getElementById('prompt').value.trim();
    const resultBox = document.getElementById('analysisResult');
    const card      = document.getElementById('resultCard');
    const btn       = this;

    if (!sheetUrl) {
        resultBox.innerHTML = '<span style="color:red;">Please enter the input sheet URL.</span>';
        return;
    }
    if (!prompt) {
        resultBox.innerHTML = '<span style="color:red;">Please enter the grading prompt.</span>';
        return;
    }

    // resultBox.innerHTML = 'Analyzing...';
    document.getElementById('actionBtns').style.display = 'none';
    card.classList.remove('show');
    btn.disabled = true;
    btn.innerHTML = '<span class="loader"></span> Analyzing...';

    try {
        const response = await fetch("{{ route('prompt.analyze') }}", {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({ sheet_url: sheetUrl, prompt: prompt })
        });

        const contentType = response.headers.get('content-type');
        if (!contentType || !contentType.includes('application/json')) {
            resultBox.innerHTML = `<span style="color:red;">Server error (${response.status}) — please try again.</span>`;
            btn.innerText = 'Analyze Prompt';
            btn.disabled = false;
            return;
        }

        const data = await response.json();
        promptAnalysis = data.analysis;
        // console.log('Prompt Analysis:', promptAnalysis);

        if (!data.success) {
            resultBox.innerHTML = response.status === 504
                ? `<span style="color:orange;">⏳ Request timed out. Please try again.</span>`
                : `<span style="color:red;">❌ ${data.message}</span>`;
            btn.innerText = 'Analyze Prompt';
            btn.disabled = false;
            return;
        }

        // const a = data.analysis;
        // const lines = [];

        // lines.push(`📋 ${a.summary || ''}`);
        // lines.push('');
        // lines.push('Components:');
        // Object.entries(a.components || {}).forEach(([name, c]) => {
        //     const score  = c.raw_max_score != null ? `out of ${c.raw_max_score}` : 'max unspecified';
        //     const weight = c.final_weight  != null ? `${c.final_weight}%` : 'weight unspecified';
        //     lines.push(`  • ${name}: ${score} — ${weight}`);
        // });

        // if (a.warnings && a.warnings.length) {
        //     lines.push('');
        //     lines.push('⚠️ Warnings:');
        //     a.warnings.forEach(w => lines.push(`  - ${w}`));
        // }

        // if (a.missing_information && a.missing_information.length) {
        //     lines.push('');
        //     lines.push('ℹ️ Missing information:');
        //     a.missing_information.forEach(m => lines.push(`  - ${m}`));
        // }

        // if (a.missing_columns && a.missing_columns.length) {
        //     lines.push('');
        //     lines.push('❌ Missing columns:');
        //     a.missing_columns.forEach(c => lines.push(`  - ${c}`));
        // }

        document.getElementById('resultText').innerText = 'Prompt analyzed — review and confirm';
        document.getElementById('resultDesc').innerHTML = 
    `<pre style="text-align:left; font-size:12px; white-space:pre-wrap; word-break:break-word;">${JSON.stringify(data.analysis, null, 2)}</pre>`;
        // أخفِ stats و ready-box
        document.querySelector('.stats').style.display = 'none';
        document.getElementById('readyBox').style.display = 'none';

        card.classList.add('show');
        document.getElementById('resultCardTitle').innerText = 'Prompt Analysis :';

        // أخفِ زر Analyze Prompt وأظهر الزرين
        btn.style.display = 'none';
        btn.innerText = 'Analyze Prompt';
        btn.disabled = false;
        document.getElementById('actionBtns').style.display = 'flex';
        resultBox.innerHTML = '';

    } catch (err) {
        resultBox.innerHTML = `<span style="color:orange;">⏳ Connection error. Please try again.</span>`;
        btn.innerText = 'Analyze Prompt';
        btn.disabled = false;
    }
});


    // ─── Re-analyze ──────────────────────────────────────────────────────────

    document.getElementById('reAnalyzeBtn').addEventListener('click', function () {
    document.getElementById('resultCard').classList.remove('show');
    document.getElementById('readyBox').style.display = '';
    document.querySelector('.stats').style.display = '';
    document.getElementById('actionBtns').style.display = 'none';
    document.getElementById('resultCardTitle').innerText = 'Result Summary :';

    // أعد إظهار زر Analyze Prompt
    const analyzeBtn = document.getElementById('analyzePromptBtn');
    analyzeBtn.style.display = '';
    analyzeBtn.click();
});


    // ─── Calculate Grades ────────────────────────────────────────────────────

   async function analyzeGrades() {
    const btn  = document.getElementById('proceedBtn');
    const card = document.getElementById('resultCard'); 

    const input1 = document.getElementById('sheet1').value.trim();
    const input2 = document.getElementById('sheet2').value.trim();
    const prompt = document.getElementById('prompt').value.trim();

    const inputs = document.querySelectorAll('input');
    const errors = document.querySelectorAll('.error-text');

    document.getElementById('resultCardTitle').innerText = 'Result Summary :';

    inputs.forEach(i => i.classList.remove('input-error'));
    errors.forEach(e => { e.style.display = 'none'; e.innerText = ''; });
    document.getElementById('errorMessage').style.display = 'none';

    if (btn.dataset.loading === 'true') return;
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
            body: JSON.stringify({ sheet1: input1, sheet2: input2, prompt: prompt })
        });

        const result = await response.json();

        if (!result.success) {
            card.classList.remove('show');
            if ((result.error || '').toLowerCase().includes('input')) {
                inputs[0].classList.add('input-error');
                errors[0].innerText = result.error;
                errors[0].style.display = 'block';
            } else if ((result.error || '').toLowerCase().includes('output')) {
                inputs[1].classList.add('input-error');
                errors[1].innerText = result.error;
                errors[1].style.display = 'block';
            } else {
                document.getElementById('errorMessage').innerText = result.error || 'Unexpected error occurred.';
                document.getElementById('errorMessage').style.display = 'block';
            }
            btn.innerText = 'Calculate Grades';
            btn.disabled = false;
            btn.dataset.loading = 'false';
            return;
        }

        // نجاح — أظهر الـ stats و ready-box
        document.querySelector('.stats').style.display = '';
        document.getElementById('readyBox').style.display = '';

        document.getElementById('avgValue').innerText = result.avg ?? '--';
        document.getElementById('maxValue').innerText = result.max ?? '--';
        document.getElementById('minValue').innerText = result.min ?? '--';
        document.getElementById('resultText').innerText = 'File processed successfully, Calculation Summary';
        document.getElementById('resultDesc').innerText = result.explanation || '';
        document.getElementById('openSheetBtn').onclick = function () {
            if (result.sheet_url) window.open(result.sheet_url, '_blank');
        };

        card.classList.add('show');
        btn.innerText = 'Done ✓';
        btn.dataset.loading = 'false';
        btn.disabled = false;

    } catch (e) {
        card.classList.remove('show');
        document.getElementById('errorMessage').innerText = 'Something went wrong. Please try again.';
        document.getElementById('errorMessage').style.display = 'block';
        btn.innerText = 'Calculate Grades';
        btn.disabled = false;
        btn.dataset.loading = 'false';
    }
}



    // ─── Analyze Grades (n8n) ────────────────────────────────────────────────

 async function analyzeGrades() {
      const btn = document.getElementById('proceedBtn');
      const card = document.getElementById('resultCard');

      const input1 = document.getElementById('sheet1').value.trim();
      const input2 = document.getElementById('sheet2').value.trim();
      const prompt = document.getElementById('prompt').value.trim();

      const inputs = document.querySelectorAll('input');
      const errors = document.querySelectorAll('.error-text');

      inputs.forEach(i => i.classList.remove('input-error'));
      errors.forEach(e => { e.style.display = 'none'; e.innerText = ''; });
      document.getElementById('errorMessage').style.display = 'none';

      if (btn.dataset.loading === 'true') return;
      btn.dataset.loading = 'true';
      btn.disabled = true;
      btn.innerHTML = '<span class="loader"></span> Processing...';

      console.log('Prompt Analysis:', promptAnalysis);

      try {
        const response = await fetch("{{ url('/send-to-n8n') }}", {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
          },
          body: JSON.stringify({ sheet1: input1, sheet2: input2, prompt: prompt, analysis: promptAnalysis })
        });

        const result = await response.json();

        if (!result.success) {
          card.classList.remove('show');

          if ((result.error || '').toLowerCase().includes('input')) {
            inputs[0].classList.add('input-error');
            errors[0].innerText = result.error;
            errors[0].style.display = 'block';
          } else if ((result.error || '').toLowerCase().includes('output')) {
            inputs[1].classList.add('input-error');
            errors[1].innerText = result.error;
            errors[1].style.display = 'block';
          } else {
            document.getElementById('errorMessage').innerText = result.error || 'Unexpected error occurred. Please try again.';
            document.getElementById('errorMessage').style.display = 'block';
          }

          btn.innerText = 'Calculate Grades';
          btn.disabled = false;
          btn.dataset.loading = 'false';
          return;
        }

        // نجاح — أظهر النتائج
        document.querySelector('.stats').style.display = '';
        document.getElementById('resultCardTitle').innerText = 'Result Summary :';
        document.getElementById('avgValue').innerText = result.avg ?? '--';
        document.getElementById('maxValue').innerText = result.max ?? '--';
        document.getElementById('minValue').innerText = result.min ?? '--';
        document.getElementById('resultText').innerText = 'File processed successfully, Calculation Summary';
        document.getElementById('resultDesc').innerText = result.explanation || '';
        document.getElementById('openSheetBtn').onclick = function () {
          if (result.sheet_url) window.open(result.sheet_url, '_blank');
        };

        document.getElementById('readyBox').style.display = '';
        card.classList.add('show');

        btn.innerText = 'Done ✓';
        btn.dataset.loading = 'false';
        btn.disabled = false;

      } catch (e) {
        card.classList.remove('show');
        document.getElementById('errorMessage').innerText = 'Something went wrong while connecting to the server. Please try again.';
        document.getElementById('errorMessage').style.display = 'block';
        btn.innerText = 'Calculate Grades';
        btn.disabled = false;
        btn.dataset.loading = 'false';
      }
    }


   document.getElementById('proceedBtn').addEventListener('click', analyzeGrades);

  </script>

</body>
</html>
<?php
require_once dirname(__DIR__) . '/config.php';
$apiScript = entry_script();
$backendUrl = backend_base();
?>
<!DOCTYPE html>
<html lang="en" class="dark scroll-smooth">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>E57 Meshify — Point Cloud to Production Mesh</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    tailwind.config = {
      darkMode: 'class',
      theme: {
        extend: {
          fontFamily: {
            sans: ['Inter', 'system-ui', 'sans-serif'],
            mono: ['JetBrains Mono', 'monospace'],
          },
          colors: {
            neon: { cyan: '#22d3ee', blue: '#3b82f6', violet: '#a78bfa' },
            void: { 950: '#030712', 900: '#0a0f1a', 800: '#111827' },
          },
          animation: {
            'glow-pulse': 'glowPulse 4s ease-in-out infinite',
            'float': 'float 7s ease-in-out infinite',
            'shimmer': 'shimmer 2.5s linear infinite',
          },
          keyframes: {
            glowPulse: {
              '0%, 100%': { opacity: '0.4' },
              '50%': { opacity: '1' },
            },
            float: {
              '0%, 100%': { transform: 'translateY(0)' },
              '50%': { transform: 'translateY(-12px)' },
            },
            shimmer: {
              '0%': { backgroundPosition: '200% center' },
              '100%': { backgroundPosition: '-200% center' },
            },
          },
        },
      },
    };
  </script>
  <style>
    :root {
      --neon-cyan: #22d3ee;
      --neon-blue: #3b82f6;
      --neon-violet: #a78bfa;
    }
    body {
      font-family: 'Inter', system-ui, sans-serif;
      background: #030712;
      color: #e2e8f0;
    }
    .mesh-bg {
      background-color: #030712;
      background-image:
        radial-gradient(ellipse 100% 60% at 50% -10%, rgba(34, 211, 238, 0.12), transparent 55%),
        radial-gradient(ellipse 60% 40% at 100% 50%, rgba(167, 139, 250, 0.08), transparent),
        radial-gradient(ellipse 50% 30% at 0% 80%, rgba(59, 130, 246, 0.1), transparent),
        linear-gradient(rgba(148, 163, 184, 0.04) 1px, transparent 1px),
        linear-gradient(90deg, rgba(148, 163, 184, 0.04) 1px, transparent 1px);
      background-size: 100% 100%, 100% 100%, 100% 100%, 56px 56px, 56px 56px;
    }
    .glass {
      background: rgba(15, 23, 42, 0.65);
      backdrop-filter: blur(20px);
      -webkit-backdrop-filter: blur(20px);
      border: 1px solid rgba(148, 163, 184, 0.1);
    }
    .glass-neon {
      background: rgba(15, 23, 42, 0.75);
      backdrop-filter: blur(24px);
      border: 1px solid rgba(34, 211, 238, 0.2);
      box-shadow: 0 0 40px rgba(34, 211, 238, 0.06), inset 0 1px 0 rgba(255,255,255,0.04);
    }
    .text-neon-gradient {
      background: linear-gradient(135deg, #22d3ee, #3b82f6, #a78bfa);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      background-clip: text;
    }
    .btn-neon {
      background: linear-gradient(135deg, #0891b2, #2563eb);
      box-shadow: 0 0 30px rgba(34, 211, 238, 0.35), 0 4px 20px rgba(37, 99, 235, 0.4);
      transition: transform 0.2s, box-shadow 0.2s;
    }
    .btn-neon:hover {
      transform: translateY(-2px);
      box-shadow: 0 0 45px rgba(34, 211, 238, 0.5), 0 8px 28px rgba(37, 99, 235, 0.5);
    }
    .drop-zone { transition: all 0.3s ease; }
    .drop-zone.drag-over {
      border-color: var(--neon-cyan);
      background: rgba(34, 211, 238, 0.06);
      box-shadow: 0 0 0 3px rgba(34, 211, 238, 0.2), 0 0 60px rgba(34, 211, 238, 0.15);
    }
    .drop-zone.has-file { border-color: #34d399; background: rgba(52, 211, 153, 0.05); }
    input[type=range] {
      -webkit-appearance: none;
      height: 6px;
      border-radius: 999px;
      background: linear-gradient(90deg, #1e293b, #334155);
    }
    input[type=range]::-webkit-slider-thumb {
      -webkit-appearance: none;
      width: 18px; height: 18px;
      border-radius: 50%;
      background: linear-gradient(135deg, #22d3ee, #3b82f6);
      box-shadow: 0 0 14px rgba(34, 211, 238, 0.6);
      cursor: pointer;
    }
    .step-item.done .step-icon { background: linear-gradient(135deg,#10b981,#059669); }
    .step-item.active .step-icon {
      background: linear-gradient(135deg, #22d3ee, #2563eb);
      animation: glowPulse 1.5s infinite;
    }
    .step-item.pending .step-icon { background: #1e293b; color: #64748b; }
    .progress-fill {
      background: linear-gradient(90deg, #0891b2, #3b82f6, #a78bfa, #0891b2);
      background-size: 200% auto;
      animation: shimmer 2.5s linear infinite;
    }
    .pipeline-card:hover {
      border-color: rgba(34, 211, 238, 0.4);
      transform: translateY(-4px);
      box-shadow: 0 12px 40px rgba(34, 211, 238, 0.1);
    }
    .pipeline-card { transition: all 0.3s ease; }
    #viewerCanvas { display: block; width: 100%; height: 100%; }
    .toast-in { animation: toastIn 0.4s ease forwards; }
    @keyframes toastIn {
      from { opacity: 0; transform: translateX(20px); }
      to { opacity: 1; transform: translateX(0); }
    }
    .orb {
      position: absolute;
      border-radius: 50%;
      filter: blur(80px);
      pointer-events: none;
    }
  </style>
</head>
<body class="mesh-bg antialiased min-h-screen">

  <div id="toastRoot" class="fixed top-5 right-5 z-[200] flex flex-col gap-2 max-w-md pointer-events-none"></div>

  <!-- ═══════════════════════════════════════════════════════════════════════════
       SECTION 1 — HERO
       ═══════════════════════════════════════════════════════════════════════════ -->
  <section id="hero" class="relative min-h-screen flex flex-col overflow-hidden">
    <div class="orb w-96 h-96 bg-cyan-500/20 -top-20 left-1/4 animate-glow-pulse"></div>
    <div class="orb w-80 h-80 bg-violet-500/15 top-1/3 right-0 animate-glow-pulse" style="animation-delay:1s"></div>

    <nav class="relative z-20 glass border-b border-slate-800/60">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4 flex items-center justify-between">
        <a href="#hero" class="flex items-center gap-3">
          <div class="w-11 h-11 rounded-xl bg-gradient-to-br from-cyan-500 to-blue-600 flex items-center justify-center shadow-lg shadow-cyan-500/30">
            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
          </div>
          <span class="font-bold text-white text-lg">E57 Meshify</span>
        </a>
        <div class="hidden md:flex items-center gap-8 text-sm text-slate-400">
          <a href="#app" class="hover:text-cyan-400 transition">App</a>
          <a href="#pipeline" class="hover:text-cyan-400 transition">Pipeline</a>
          <a href="#features" class="hover:text-cyan-400 transition">Features</a>
        </div>
        <div id="heroStatus" class="flex items-center gap-2 text-xs font-medium px-4 py-2 rounded-full glass-neon">
          <span id="statusDot" class="w-2.5 h-2.5 rounded-full bg-slate-500"></span>
          <span id="statusText" class="text-slate-300">Checking API…</span>
        </div>
      </div>
    </nav>

    <div class="relative z-10 flex-1 flex flex-col items-center justify-center text-center px-4 pb-24 pt-16">
      <p class="text-cyan-400/90 text-sm font-semibold uppercase tracking-[0.25em] mb-6">Local-first · AI-enhanced · Print-ready</p>
      <h1 class="text-4xl sm:text-6xl lg:text-7xl font-black text-white leading-[1.08] max-w-5xl mb-6">
        Point Cloud to<br>
        <span class="text-neon-gradient">Production Mesh</span><br>
        in Seconds
      </h1>
      <p class="text-slate-400 text-lg sm:text-xl max-w-2xl mb-10 leading-relaxed">
        Transform industrial <code class="text-cyan-400 font-mono text-base">.e57</code> laser scans into watertight, 3D-printable
        <code class="text-cyan-400 font-mono text-base">.obj</code> meshes — with Isolation Forest denoising, Ball Pivoting reconstruction, and Taubin optimization.
      </p>
      <div class="flex flex-col sm:flex-row gap-4 items-center">
        <a href="#app" class="btn-neon inline-flex items-center gap-2 px-8 py-4 rounded-2xl text-white font-bold text-lg">
          Launch Processing App
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3"/></svg>
        </a>
        <span class="text-slate-500 text-sm font-mono">Backend: <?= htmlspecialchars($backendUrl) ?></span>
      </div>
      <div class="mt-20 animate-float opacity-60">
        <svg class="w-8 h-8 text-cyan-500/50 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3"/></svg>
      </div>
    </div>
  </section>

  <!-- ═══════════════════════════════════════════════════════════════════════════
       SECTION 2 — INTERACTIVE APP
       ═══════════════════════════════════════════════════════════════════════════ -->
  <section id="app" class="relative py-24 px-4 sm:px-6 lg:px-8 border-t border-slate-800/80">
    <div class="max-w-7xl mx-auto">
      <div class="text-center mb-14">
        <h2 class="text-3xl sm:text-4xl font-bold text-white mb-3">Processing Workspace</h2>
        <p class="text-slate-400 max-w-xl mx-auto">Upload your scan, tune parameters, and preview the mesh before download.</p>
      </div>

      <div class="grid lg:grid-cols-12 gap-8">
        <div class="lg:col-span-5 space-y-6">
          <div id="dropZone" class="drop-zone glass-neon rounded-2xl border-2 border-dashed border-slate-600 p-10 cursor-pointer group">
            <input type="file" id="fileInput" accept=".e57" class="hidden">
            <div class="text-center pointer-events-none">
              <div class="w-18 h-18 w-16 h-16 mx-auto mb-4 rounded-2xl bg-slate-800/80 flex items-center justify-center border border-cyan-500/20 group-hover:border-cyan-400/50 transition">
                <svg class="w-8 h-8 text-cyan-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg>
              </div>
              <p id="dropLabel" class="text-white font-semibold text-lg">Drag & drop your .e57 scan</p>
              <p id="dropSub" class="text-slate-500 text-sm mt-1">or click to browse</p>
              <p id="fileMeta" class="mt-3 text-emerald-400 text-sm font-medium hidden"></p>
            </div>
          </div>

          <div class="glass-neon rounded-2xl p-6 space-y-5">
            <h3 class="text-white font-semibold text-sm uppercase tracking-wider text-cyan-400/80">Parameters</h3>
            <div>
              <div class="flex justify-between text-sm mb-2">
                <label class="text-slate-300">Outlier Removal Sensitivity</label>
                <span id="contaminationVal" class="text-cyan-400 font-mono">3.0%</span>
              </div>
              <input type="range" id="contamination" min="0.5" max="15" step="0.5" value="3" class="w-full">
            </div>
            <div>
              <div class="flex justify-between text-sm mb-2">
                <label class="text-slate-300">BPA Ball Radius Scale</label>
                <span id="ballRadiusVal" class="text-cyan-400 font-mono">4.0×</span>
              </div>
              <input type="range" id="ballRadius" min="1" max="12" step="0.5" value="4" class="w-full">
            </div>
            <div>
              <div class="flex justify-between text-sm mb-2">
                <label class="text-slate-300">Taubin Smoothing Iterations</label>
                <span id="smoothingVal" class="text-cyan-400 font-mono">5</span>
              </div>
              <input type="range" id="smoothing" min="1" max="25" step="1" value="5" class="w-full">
            </div>
            <div class="flex items-center justify-between pt-2 border-t border-slate-700/60">
              <label class="text-slate-400 text-sm">Quality preset</label>
              <select id="quality" class="bg-slate-900 border border-slate-600 rounded-lg px-3 py-2 text-sm text-white focus:ring-2 focus:ring-cyan-500 outline-none">
                <option value="High">High</option>
                <option value="Medium" selected>Medium</option>
                <option value="Low">Low</option>
              </select>
            </div>
            <button id="processBtn" type="button" class="btn-neon w-full py-4 rounded-xl font-bold text-white flex items-center justify-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed disabled:transform-none">
              <span id="btnText">Generate Mesh</span>
            </button>
          </div>
        </div>

        <div class="lg:col-span-7 space-y-6">
          <div id="progressPanel" class="glass-neon rounded-2xl p-6 hidden">
            <div class="flex justify-between mb-4">
              <h3 class="text-white font-semibold">Live Pipeline</h3>
              <span id="progressPercent" class="text-cyan-400 font-mono text-sm">0%</span>
            </div>
            <div class="h-2 bg-slate-800 rounded-full overflow-hidden mb-6">
              <div id="progressBar" class="progress-fill h-full rounded-full transition-all duration-500" style="width:0%"></div>
            </div>
            <ul id="stepList" class="space-y-3"></ul>
          </div>

          <div id="idlePanel" class="glass rounded-2xl p-12 text-center border border-slate-700/50 min-h-[280px] flex flex-col items-center justify-center">
            <div class="w-24 h-24 rounded-full bg-slate-800/50 flex items-center justify-center mb-4 border border-cyan-500/10">
              <svg class="w-12 h-12 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M14 10l-2 1m0 0l-2-1m2 1v2.5M20 7l-2 1m2-1l-2-1m2 1v2.5M14 4l-2-1-2 1"/></svg>
            </div>
            <p class="text-slate-400">Drop an <code class="text-cyan-400">.e57</code> file and click <strong class="text-white">Generate Mesh</strong></p>
            <p id="offlineHint" class="text-amber-500/80 text-sm mt-3 hidden">API offline — start Flask with <code class="font-mono">python app.py</code></p>
          </div>

          <div id="resultPanel" class="hidden">
            <div class="grid md:grid-cols-2 gap-6">
              <div class="glass-neon rounded-2xl p-6 space-y-4">
                <h3 class="text-white font-bold flex items-center gap-2">
                  <span class="w-2 h-2 rounded-full bg-emerald-400 shadow shadow-emerald-400/50"></span>
                  Mesh Ready
                </h3>
                <dl class="space-y-3 text-sm">
                  <div class="flex justify-between border-b border-slate-700/50 pb-2"><dt class="text-slate-500">Points</dt><dd id="statPoints" class="font-mono text-white">—</dd></div>
                  <div class="flex justify-between border-b border-slate-700/50 pb-2"><dt class="text-slate-500">Triangles</dt><dd id="statTriangles" class="font-mono text-white">—</dd></div>
                  <div class="flex justify-between border-b border-slate-700/50 pb-2"><dt class="text-slate-500">File size</dt><dd id="statSize" class="font-mono text-white">—</dd></div>
                  <div class="flex justify-between"><dt class="text-slate-500">Output</dt><dd id="statFile" class="font-mono text-cyan-400 text-xs truncate max-w-[140px]">—</dd></div>
                </dl>
                <a id="downloadBtn" href="#" download class="flex w-full items-center justify-center gap-2 py-3.5 rounded-xl font-bold bg-gradient-to-r from-emerald-600 to-teal-500 text-white shadow-lg shadow-emerald-500/25 hover:brightness-110 transition">
                  Download .OBJ Mesh
                </a>
                <button id="newJobBtn" type="button" class="w-full text-sm text-slate-500 hover:text-white transition">Process another file</button>
              </div>
              <div class="glass-neon rounded-2xl overflow-hidden flex flex-col min-h-[300px]">
                <div class="px-4 py-3 border-b border-slate-700/60 flex justify-between text-sm">
                  <span class="text-white font-medium">3D Preview</span>
                  <span class="text-slate-500">Orbit · Zoom</span>
                </div>
                <div id="viewerWrap" class="relative flex-1 min-h-[260px] bg-void-950">
                  <canvas id="viewerCanvas"></canvas>
                  <div id="viewerLoading" class="absolute inset-0 flex items-center justify-center bg-void-950/90 hidden">
                    <span class="text-cyan-400/80 text-sm animate-pulse">Loading mesh…</span>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- ═══════════════════════════════════════════════════════════════════════════
       SECTION 3 — HOW IT WORKS
       ═══════════════════════════════════════════════════════════════════════════ -->
  <section id="pipeline" class="py-24 px-4 sm:px-6 lg:px-8 bg-void-900/50 border-t border-slate-800/80">
    <div class="max-w-7xl mx-auto">
      <div class="text-center mb-16">
        <h2 class="text-3xl sm:text-4xl font-bold text-white mb-3">How It Works</h2>
        <p class="text-slate-400 max-w-2xl mx-auto">A four-stage engineering pipeline from raw scan to manifold mesh.</p>
      </div>
      <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-6">
        <?php
        $steps = [
          ['01', 'Ingest Raw E57', 'PyE57 reads every scan with ignore_missing_fields — resilient to missing intensity or RGB.', 'M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12'],
          ['02', 'AI Noise Filtering', 'Scikit-learn Isolation Forest removes statistical outliers before downsampling.', 'M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z'],
          ['03', 'Ball Pivoting Reconstruction', 'Open3D BPA with adaptive multi-radius balls fitted to local point density.', 'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4'],
          ['04', 'Manifold Optimization', 'Degenerate triangle cleanup + Taubin smoothing for print-ready topology.', 'M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z'],
        ];
        foreach ($steps as $s): ?>
        <article class="pipeline-card glass rounded-2xl p-6 border border-slate-700/50">
          <div class="text-4xl font-black text-neon-gradient font-mono mb-3"><?= $s[0] ?></div>
          <div class="w-12 h-12 rounded-xl bg-cyan-500/10 flex items-center justify-center mb-4 border border-cyan-500/20">
            <svg class="w-6 h-6 text-cyan-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="<?= $s[3] ?>"/></svg>
          </div>
          <h3 class="text-white font-bold mb-2"><?= htmlspecialchars($s[1]) ?></h3>
          <p class="text-slate-500 text-sm leading-relaxed"><?= htmlspecialchars($s[2]) ?></p>
        </article>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <!-- ═══════════════════════════════════════════════════════════════════════════
       SECTION 4 — FEATURES + FOOTER
       ═══════════════════════════════════════════════════════════════════════════ -->
  <section id="features" class="py-24 px-4 sm:px-6 lg:px-8 border-t border-slate-800/80">
    <div class="max-w-7xl mx-auto">
      <div class="text-center mb-14">
        <h2 class="text-3xl sm:text-4xl font-bold text-white mb-3">Technical Features</h2>
        <p class="text-slate-400">Built for surveyors, fabricators, and reverse-engineering workflows.</p>
      </div>
      <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-5">
        <?php
        $feats = [
          ['Resilient E57 Parsing', 'Handles scans missing intensity, RGB, or individual channels via ignore_missing_fields.'],
          ['Manifold & Print-Ready Output', 'Removes degenerate faces and applies Taubin smoothing for clean STL/OBJ export.'],
          ['Private & Offline Processing', 'Run Flask on your machine or VPS — data never leaves your infrastructure.'],
          ['Adaptive BPA Radii', 'Ball sizes auto-scale from nearest-neighbor point spacing.'],
          ['Live Progress Tracking', 'Poll async jobs for real-time pipeline step completion.'],
          ['In-Browser 3D Preview', 'Inspect the generated mesh with Three.js before downloading.'],
        ];
        foreach ($feats as $f): ?>
        <div class="glass rounded-xl p-5 border border-slate-700/40 hover:border-cyan-500/30 transition">
          <h4 class="text-white font-semibold mb-2"><?= htmlspecialchars($f[0]) ?></h4>
          <p class="text-slate-500 text-sm"><?= htmlspecialchars($f[1]) ?></p>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <footer class="border-t border-slate-800 bg-void-950 py-12 px-4">
    <div class="max-w-7xl mx-auto flex flex-col md:flex-row items-center justify-between gap-6">
      <div class="flex items-center gap-3">
        <div class="w-9 h-9 rounded-lg bg-gradient-to-br from-cyan-500 to-blue-600 flex items-center justify-center">
          <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4"/></svg>
        </div>
        <div>
          <p class="text-white font-semibold">E57 Meshify</p>
          <p class="text-slate-600 text-xs">© <?= date('Y') ?> · Point cloud to production mesh</p>
        </div>
      </div>
      <div class="flex gap-8 text-sm text-slate-500">
        <a href="#app" class="hover:text-cyan-400 transition">App</a>
        <a href="#pipeline" class="hover:text-cyan-400 transition">Pipeline</a>
        <a href="#features" class="hover:text-cyan-400 transition">Features</a>
      </div>
      <p class="text-slate-600 text-xs font-mono">API → <?= htmlspecialchars($backendUrl) ?></p>
    </div>
  </footer>

  <script type="importmap">
  { "imports": { "three": "https://cdn.jsdelivr.net/npm/three@0.160.0/build/three.module.js", "three/addons/": "https://cdn.jsdelivr.net/npm/three@0.160.0/examples/jsm/" } }
  </script>
  <script type="module">
    import * as THREE from 'three';
    import { OrbitControls } from 'three/addons/controls/OrbitControls.js';
    import { OBJLoader } from 'three/addons/loaders/OBJLoader.js';

    const API_SCRIPT = <?= json_encode($apiScript) ?>;
    const BACKEND_URL = <?= json_encode($backendUrl) ?>;
    const MAX_BYTES = <?= (int) UPLOAD_MAX_BYTES ?>;
    const API = (action, params = '') => `${API_SCRIPT}?api=${action}${params}`;

    const PIPELINE_STEPS = [
      { id: 'ingesting', label: 'Ingesting File' },
      { id: 'noise_reduction', label: 'AI Noise Reduction' },
      { id: 'geometry_prep', label: 'Normal Estimation' },
      { id: 'surface_reconstruction', label: 'Ball Pivoting Reconstruction' },
      { id: 'mesh_optimization', label: 'Taubin Smoothing' },
    ];

    let selectedFile = null;
    let pollTimer = null;
    let viewer = null;
    let apiOnline = false;

    const $ = (id) => document.getElementById(id);

    async function parseJsonResponse(res) {
      const text = await res.text();
      if (!text || !text.trim()) {
        throw new Error(
          `Empty response from server (HTTP ${res.status}). Start Flask at ${BACKEND_URL} — run: cd Shubh-Ps-main && python app.py`
        );
      }
      try {
        return JSON.parse(text);
      } catch {
        const preview = text.trim().slice(0, 100);
        throw new Error(`Invalid JSON (HTTP ${res.status}): ${preview}`);
      }
    }

    function toast(message, type = 'info') {
      const border = { info: 'border-cyan-500', success: 'border-emerald-500', error: 'border-red-500', warn: 'border-amber-500' };
      const el = document.createElement('div');
      el.className = `toast-in pointer-events-auto glass-neon rounded-xl px-4 py-3 border-l-4 ${border[type] || border.info} text-sm text-white shadow-2xl`;
      el.textContent = message;
      $('toastRoot').appendChild(el);
      setTimeout(() => { el.style.opacity = '0'; el.style.transition = 'opacity 0.3s'; setTimeout(() => el.remove(), 300); }, 6000);
    }

    function setApiStatus(online) {
      apiOnline = online;
      if (online) {
        $('statusDot').className = 'w-2.5 h-2.5 rounded-full bg-emerald-400 shadow-lg shadow-emerald-400/50';
        $('statusText').textContent = 'API Online';
        $('offlineHint').classList.add('hidden');
      } else {
        $('statusDot').className = 'w-2.5 h-2.5 rounded-full bg-red-400 shadow-lg shadow-red-400/40';
        $('statusText').textContent = 'API Offline';
        $('offlineHint').classList.remove('hidden');
      }
    }

    async function checkHealth() {
      try {
        const res = await fetch(API('health'), { cache: 'no-store' });
        const data = await parseJsonResponse(res);
        setApiStatus(res.ok && data.status === 'online');
      } catch {
        setApiStatus(false);
      }
    }
    checkHealth();
    setInterval(checkHealth, 30000);

    $('contamination').addEventListener('input', (e) => {
      $('contaminationVal').textContent = `${parseFloat(e.target.value).toFixed(1)}%`;
    });
    $('ballRadius').addEventListener('input', (e) => {
      $('ballRadiusVal').textContent = `${parseFloat(e.target.value).toFixed(1)}×`;
    });
    $('smoothing').addEventListener('input', (e) => {
      $('smoothingVal').textContent = e.target.value;
    });
    $('quality').addEventListener('change', (e) => {
      const map = { High: 2, Medium: 4, Low: 8 };
      $('ballRadius').value = map[e.target.value] || 4;
      $('ballRadiusVal').textContent = `${$('ballRadius').value}×`;
    });

    const dropZone = $('dropZone');
    const fileInput = $('fileInput');

    function setFile(file) {
      if (!file) return;
      if (!file.name.toLowerCase().endsWith('.e57')) {
        toast('Please upload a valid .e57 file.', 'warn');
        return;
      }
      if (file.size > MAX_BYTES) {
        toast(`File exceeds ${(MAX_BYTES / 1048576).toFixed(0)} MB limit.`, 'error');
        return;
      }
      selectedFile = file;
      dropZone.classList.add('has-file');
      $('dropLabel').textContent = file.name;
      $('dropSub').textContent = `${(file.size / 1048576).toFixed(2)} MB · Ready`;
      $('fileMeta').textContent = '✓ Validated';
      $('fileMeta').classList.remove('hidden');
    }

    fileInput.addEventListener('change', (e) => setFile(e.target.files[0]));
    dropZone.addEventListener('click', () => fileInput.click());
    ['dragenter', 'dragover'].forEach((ev) => {
      dropZone.addEventListener(ev, (e) => { e.preventDefault(); dropZone.classList.add('drag-over'); });
    });
    ['dragleave', 'drop'].forEach((ev) => {
      dropZone.addEventListener(ev, (e) => { e.preventDefault(); dropZone.classList.remove('drag-over'); });
    });
    dropZone.addEventListener('drop', (e) => setFile(e.dataTransfer.files[0]));

    function renderSteps(completed = [], current = null) {
      const total = PIPELINE_STEPS.length;
      const pct = Math.round((completed.length / total) * 100);
      $('progressPercent').textContent = `${pct}%`;
      $('progressBar').style.width = `${Math.max(pct, current ? pct + 6 : 0)}%`;
      $('stepList').innerHTML = PIPELINE_STEPS.map((step) => {
        let state = 'pending';
        if (completed.includes(step.id)) state = 'done';
        else if (current === step.id) state = 'active';
        const icon = state === 'done' ? '✓' : state === 'active' ? '◉' : '○';
        return `<li class="step-item ${state} flex items-center gap-3 text-sm">
          <span class="step-icon w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold text-white">${icon}</span>
          <span class="${state === 'pending' ? 'text-slate-500' : 'text-white'}">${step.label}</span>
        </li>`;
      }).join('');
    }

    function showProgress() {
      $('idlePanel').classList.add('hidden');
      $('resultPanel').classList.add('hidden');
      $('progressPanel').classList.remove('hidden');
      renderSteps([], 'ingesting');
    }

    function resetUI() {
      if (pollTimer) clearInterval(pollTimer);
      pollTimer = null;
      $('progressPanel').classList.add('hidden');
      $('resultPanel').classList.add('hidden');
      $('idlePanel').classList.remove('hidden');
      $('processBtn').disabled = false;
      $('btnText').textContent = 'Generate Mesh';
    }

    $('newJobBtn').addEventListener('click', resetUI);

    function initViewer() {
      const canvas = $('viewerCanvas');
      const wrap = $('viewerWrap');
      const w = wrap.clientWidth;
      const h = wrap.clientHeight || 280;
      const scene = new THREE.Scene();
      scene.background = new THREE.Color(0x030712);
      const camera = new THREE.PerspectiveCamera(45, w / h, 0.01, 1000);
      camera.position.set(2, 1.5, 2);
      const renderer = new THREE.WebGLRenderer({ canvas, antialias: true });
      renderer.setSize(w, h);
      renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
      const controls = new OrbitControls(camera, renderer.domElement);
      controls.enableDamping = true;
      scene.add(new THREE.AmbientLight(0xffffff, 0.6));
      const dir = new THREE.DirectionalLight(0xffffff, 0.9);
      dir.position.set(5, 10, 5);
      scene.add(dir);
      scene.add(new THREE.GridHelper(4, 20, 0x1e3a5f, 0x0f172a));
      let meshObject = null;

      function fitCamera(obj) {
        const box = new THREE.Box3().setFromObject(obj);
        const center = box.getCenter(new THREE.Vector3());
        const size = box.getSize(new THREE.Vector3());
        const dist = Math.max(size.x, size.y, size.z) * 1.8;
        camera.position.set(center.x + dist, center.y + dist * 0.5, center.z + dist);
        controls.target.copy(center);
        controls.update();
      }

      function loadObj(url) {
        $('viewerLoading').classList.remove('hidden');
        if (meshObject) { scene.remove(meshObject); meshObject = null; }
        new OBJLoader().load(
          url,
          (obj) => {
            obj.traverse((c) => {
              if (c.isMesh) {
                c.material = new THREE.MeshStandardMaterial({
                  color: 0x22d3ee, metalness: 0.2, roughness: 0.5,
                });
              }
            });
            meshObject = obj;
            scene.add(obj);
            fitCamera(obj);
            $('viewerLoading').classList.add('hidden');
          },
          undefined,
          () => {
            $('viewerLoading').classList.add('hidden');
            toast('3D preview failed — download still works.', 'warn');
          }
        );
      }

      (function animate() {
        requestAnimationFrame(animate);
        controls.update();
        renderer.render(scene, camera);
      })();

      window.addEventListener('resize', () => {
        const nw = wrap.clientWidth;
        const nh = wrap.clientHeight || 280;
        camera.aspect = nw / nh;
        camera.updateProjectionMatrix();
        renderer.setSize(nw, nh);
      });

      return { loadObj };
    }

    async function pollStatus(jobId) {
      const res = await fetch(API('status', `&job_id=${encodeURIComponent(jobId)}`));
      const data = await parseJsonResponse(res);
      if (!res.ok) throw new Error(data.error || 'Status check failed');
      renderSteps(data.completed_steps || [], data.current_step);
      if (data.status === 'complete' && data.result) {
        showResult(data.result);
        return true;
      }
      if (data.status === 'error') throw new Error(data.error || 'Processing failed');
      return false;
    }

    function showResult(result) {
      $('progressPanel').classList.add('hidden');
      $('resultPanel').classList.remove('hidden');
      const meshProxy = API('mesh', `&file=${encodeURIComponent(result.mesh_file)}`);
      $('statPoints').textContent = (result.points_processed ?? 0).toLocaleString();
      $('statTriangles').textContent = (result.triangles_generated ?? 0).toLocaleString();
      $('statSize').textContent = result.file_size_human || '—';
      $('statFile').textContent = result.mesh_file || '—';
      $('downloadBtn').href = meshProxy;
      if (!viewer) viewer = initViewer();
      viewer.loadObj(meshProxy);
      $('processBtn').disabled = false;
      $('btnText').textContent = 'Generate Mesh';
      toast('Mesh generated successfully!', 'success');
    }

    $('processBtn').addEventListener('click', async () => {
      if (!selectedFile) {
        toast('Select or drop an .e57 file first.', 'warn');
        return;
      }
      if (!apiOnline) {
        toast(`Flask API is offline. Start it: python app.py (${BACKEND_URL})`, 'error');
        return;
      }

      $('processBtn').disabled = true;
      $('btnText').textContent = 'Processing…';
      showProgress();

      const formData = new FormData();
      formData.append('file', selectedFile);
      formData.append('quality', $('quality').value);
      formData.append('contamination', (parseFloat($('contamination').value) / 100).toFixed(4));
      formData.append('ball_radius_multiplier', $('ballRadius').value);
      formData.append('smoothing_iterations', $('smoothing').value);

      try {
        const controller = new AbortController();
        const timeout = setTimeout(() => controller.abort(), 600000);
        const res = await fetch(API('process'), { method: 'POST', body: formData, signal: controller.signal });
        clearTimeout(timeout);
        const data = await parseJsonResponse(res);

        if (!res.ok) throw new Error(data.error || `Server error (${res.status})`);
        if (!data.job_id) throw new Error(data.error || 'No job_id — is Flask running the updated app.py?');

        toast('Pipeline started…', 'info');
        const jobId = data.job_id;
        if (!(await pollStatus(jobId))) {
          pollTimer = setInterval(async () => {
            try {
              if (await pollStatus(jobId)) clearInterval(pollTimer);
            } catch (e) {
              clearInterval(pollTimer);
              handleError(e);
            }
          }, 1500);
        }
      } catch (err) {
        handleError(err);
      }
    });

    function handleError(err) {
      if (pollTimer) clearInterval(pollTimer);
      pollTimer = null;
      $('processBtn').disabled = false;
      $('btnText').textContent = 'Generate Mesh';
      const msg = err.name === 'AbortError'
        ? 'Request timed out. Increase PHP/Flask timeouts for large scans.'
        : (err.message || 'Unknown error');
      toast(msg, 'error');
      resetUI();
    }
  </script>
</body>
</html>

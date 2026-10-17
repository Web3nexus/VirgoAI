// OpenArt Studio Engine (Generative Mock Pipeline, Prompt Enhancer, Ratios & Presets)

window.enhanceStudioPrompt = function() {
  const el = document.getElementById('studio-prompt-input');
  if (!el) return;
  const val = el.value.trim();
  const enhancements = [
    ", 8k resolution, photorealistic cinematic lighting, raytraced reflections, Unreal Engine 5 render, shot on 35mm lens, masterpiece",
    ", dramatic volumetric backlight, hyperdetailed texture, shallow depth of field, award-winning composition, sharp focus",
    ", atmospheric color grading, cinematic haze, highly intricate details, studio lighting setup, award winning masterpiece"
  ];
  const randomAdd = enhancements[Math.floor(Math.random() * enhancements.length)];
  el.value = val + randomAdd;
};

window.executeStudioGeneration = function(cost = 1) {
  if (window.OpenArtState.credits < cost) {
    openModal('credits');
    return;
  }

  window.OpenArtState.credits -= cost;
  localStorage.setItem('openart_credits', window.OpenArtState.credits);
  document.querySelectorAll('.credit-balance-display').forEach(el => {
    el.innerText = `${window.OpenArtState.credits} Credits`;
  });

  const progressCard = document.getElementById('generation-progress-card');
  const progressBar = document.getElementById('gen-bar');
  const progressPercent = document.getElementById('gen-percent');

  if (progressCard && progressBar && progressPercent) {
    progressCard.classList.remove('hidden');
    progressBar.style.width = '10%';
    progressPercent.innerText = '15%';

    let progress = 15;
    const interval = setInterval(() => {
      progress += 20;
      progressBar.style.width = progress + '%';
      progressPercent.innerText = progress + '%';

      if (progress >= 100) {
        clearInterval(interval);
        setTimeout(() => {
          progressCard.classList.add('hidden');
          appendStudioResult();
        }, 300);
      }
    }, 180);
  } else {
    alert(`Render queued with ${cost} credit(s)! Completed.`);
  }
};

function appendStudioResult() {
  const grid = document.getElementById('creations-grid');
  if (!grid) return;
  const promptInput = document.getElementById('studio-prompt-input');
  const promptText = promptInput ? promptInput.value : 'New Masterpiece';

  const card = document.createElement('div');
  card.className = 'group relative rounded-2xl overflow-hidden bg-ink-1 border border-brand/60 aspect-square cursor-pointer shadow-lg shadow-brand/10';
  card.onclick = () => openModal('image-detail');
  card.innerHTML = `
    <img src="https://images.unsplash.com/photo-1579783900882-c0d3dad7b119?auto=format&fit=crop&w=600&q=80" class="w-full h-full object-cover group-hover:scale-105 transition duration-300">
    <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-transparent to-transparent opacity-0 group-hover:opacity-100 transition p-3 flex flex-col justify-end">
      <span class="text-xs font-bold text-white truncate">${promptText}</span>
      <div class="flex items-center gap-1.5 mt-1">
        <span class="text-[10px] px-2 py-0.5 rounded bg-brand/30 text-brand font-medium">Remix</span>
        <span class="text-[10px] px-2 py-0.5 rounded bg-white/20 text-white font-medium" onclick="event.stopPropagation(); openModal('upscale')">Topaz 4x</span>
      </div>
    </div>
  `;
  grid.prepend(card);
}

window.setRatioAction = function(ratio, btn) {
  document.querySelectorAll('.ratio-btn').forEach(b => {
    b.className = 'ratio-btn py-2 rounded-lg bg-ink-2 border border-white/5 text-slate-11 hover:text-white';
  });
  btn.className = 'ratio-btn py-2 rounded-lg bg-brand/20 border border-brand text-white font-semibold';
  window.OpenArtState.activeRatio = ratio;
};

window.setStyleAction = function(styleName, btn) {
  document.querySelectorAll('.style-chip').forEach(b => {
    b.className = 'style-chip p-2 rounded-lg bg-ink-2 border border-white/5 text-slate-11 hover:text-white text-left';
  });
  btn.className = 'style-chip p-2 rounded-lg bg-brand/20 border border-brand text-white text-left font-medium';
  window.OpenArtState.activeStyle = styleName;
};


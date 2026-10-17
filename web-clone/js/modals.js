// OpenArt Global Modals System (9 Complete Modals with Hugeicons Integration)

(function() {
  // Global State
  window.OpenArtState = {
    credits: parseInt(localStorage.getItem('openart_credits') || '50', 10),
    activeModel: localStorage.getItem('openart_model') || 'Seedream 5.0 (Latest)',
    activeStyle: 'Photorealistic',
    activeRatio: '1:1',
    userLoggedIn: localStorage.getItem('openart_logged_in') === 'true'
  };

  const modalTemplates = `
  <!-- MODAL 1: AUTHENTICATION -->
  <div id="modal-auth" class="oa-modal hidden fixed inset-0 z-[100] bg-black/80 backdrop-blur-md flex items-center justify-center p-4">
    <div class="bg-ink-1 border border-white/15 rounded-3xl w-full max-w-md p-6 shadow-2xl relative text-left">
      <button onclick="closeModal('auth')" class="absolute right-4 top-4 text-slate-8 hover:text-white transition">
        <i class="hgi-stroke hgi-cancel-01 text-base"></i>
      </button>
      <div class="text-center mb-6">
        <div class="w-11 h-11 rounded-xl bg-brand/20 text-brand flex items-center justify-center mx-auto mb-3 text-lg font-bold">
          <i class="hgi-stroke hgi-ai-magic text-xl"></i>
        </div>
        <h3 class="font-heading font-extrabold text-2xl text-white">Sign In to OpenArt</h3>
        <p class="text-xs text-slate-11 mt-1">Access 100+ creative AI models with daily free credits</p>
      </div>
      <div class="space-y-3">
        <button onclick="simulateLogin('Google')" class="w-full py-2.5 px-4 rounded-xl bg-white/5 hover:bg-white/10 border border-white/15 text-white font-medium text-xs flex items-center justify-center gap-2.5 transition">
          <svg class="w-4 h-4" viewBox="0 0 24 24"><path fill="#EA4335" d="M12 5c1.7 0 3 .6 4 1.5l3-3C17.2 1.8 14.8 1 12 1 7.5 1 3.7 3.6 1.9 7.3l3.7 2.9C6.5 7.3 9 5 12 5z"/><path fill="#4285F4" d="M23.5 12.3c0-.8-.1-1.6-.2-2.3H12v4.5h6.5c-.3 1.5-1.1 2.8-2.4 3.7l3.7 2.9c2.2-2 3.7-5 3.7-8.8z"/><path fill="#FBBC05" d="M5.6 14.8c-.2-.7-.4-1.5-.4-2.3s.2-1.6.4-2.3L1.9 7.3C.7 9.7 0 12 0 14.5s.7 4.8 1.9 7.2l3.7-2.9z"/><path fill="#34A853" d="M12 24c3.2 0 6-1.1 8-3l-3.7-2.9c-1.1.7-2.5 1.2-4.3 1.2-3 0-5.5-2-6.4-4.8L1.9 17.4C3.7 21.1 7.5 24 12 24z"/></svg>
          Continue with Google
        </button>
        <button onclick="simulateLogin('Discord')" class="w-full py-2.5 px-4 rounded-xl bg-[#5865F2]/20 hover:bg-[#5865F2]/30 border border-[#5865F2]/40 text-white font-medium text-xs flex items-center justify-center gap-2.5 transition">
          <svg class="w-4 h-4 fill-current" viewBox="0 0 24 24"><path d="M20.3 4.4A19.7 19.7 0 0 0 15.3 2.9a.1.1 0 0 0-.1.1 13.7 13.7 0 0 0-.6 1.3 18.2 18.2 0 0 0-5.2 0 13 13 0 0 0-.6-1.3.1.1 0 0 0-.1-.1 19.7 19.7 0 0 0-5 1.5.1.1 0 0 0-.1.1C.7 9 .1 13.4.4 17.8a.1.1 0 0 0 .1.1 19.8 19.8 0 0 0 6 3 .1.1 0 0 0 .1 0 14.2 14.2 0 0 0 1.2-2 .1.1 0 0 0-.1-.1 13 13 0 0 1-1.9-.9.1.1 0 0 1 0-.2c.1-.1.3-.2.4-.3a14.1 14.1 0 0 0 11.6 0c.1.1.3.2.4.3a.1.1 0 0 1 0 .2 13 13 0 0 1-1.9.9.1.1 0 0 0-.1.1 14.4 14.4 0 0 0 1.2 2 .1.1 0 0 0 .1 0 19.8 19.8 0 0 0 6-3 .1.1 0 0 0 .1-.1c.4-4.9-.7-9.3-3.4-13.4a.1.1 0 0 0-.1-.1zM8.5 14.8c-1.1 0-2-.9-2-2.1s.9-2.1 2-2.1 2 .9 2 2.1-.9 2.1-2 2.1zm7 0c-1.1 0-2-.9-2-2.1s.9-2.1 2-2.1 2 .9 2 2.1-.9 2.1-2 2.1z"/></svg>
          Continue with Discord
        </button>
      </div>
      <div class="flex items-center gap-3 my-5">
        <div class="h-px bg-white/10 flex-1"></div>
        <span class="text-[10px] text-slate-9 uppercase font-mono tracking-wider">or email magic link</span>
        <div class="h-px bg-white/10 flex-1"></div>
      </div>
      <div class="space-y-3">
        <div class="relative">
          <input id="auth-email-input" type="email" placeholder="creator@domain.com" class="w-full bg-ink-2 border border-white/10 rounded-xl pl-9 pr-4 py-2.5 text-xs text-white placeholder-slate-9 outline-none focus:border-brand">
          <i class="hgi-stroke hgi-mail-01 text-xs text-slate-9 absolute left-3 top-1/2 -translate-y-1/2"></i>
        </div>
        <button onclick="simulateLogin('Email Magic Link')" class="w-full py-2.5 rounded-xl bg-brand hover:bg-brand-hover text-white font-bold text-xs transition shadow-lg shadow-brand/20 flex items-center justify-center gap-1.5">
          <span>Send Magic Link</span>
          <i class="hgi-stroke hgi-arrow-right-01 text-xs"></i>
        </button>
      </div>
      <p class="text-[10px] text-slate-9 text-center mt-4">By continuing you agree to OpenArt Terms of Service and Privacy Policy.</p>
    </div>
  </div>

  <!-- MODAL 2: IMAGE DETAIL & PROMPT INSPECTOR -->
  <div id="modal-image-detail" class="oa-modal hidden fixed inset-0 z-[100] bg-black/85 backdrop-blur-md flex items-center justify-center p-4">
    <div class="bg-ink-1 border border-white/15 rounded-3xl w-full max-w-4xl p-6 shadow-2xl relative flex flex-col md:flex-row gap-6 max-h-[90vh] overflow-y-auto custom-scrollbar text-left">
      <button onclick="closeModal('image-detail')" class="absolute right-4 top-4 text-slate-8 hover:text-white transition z-10">
        <i class="hgi-stroke hgi-cancel-01 text-base"></i>
      </button>
      <div class="flex-1 bg-black/70 rounded-2xl overflow-hidden flex items-center justify-center border border-white/10">
        <img id="detail-modal-img" src="https://images.unsplash.com/photo-1618005182384-a83a8bd57fbe?auto=format&fit=crop&w=1000&q=80" class="max-h-[500px] w-auto object-contain">
      </div>
      <div class="w-full md:w-80 flex flex-col justify-between">
        <div>
          <span class="text-[10px] font-mono text-brand font-semibold uppercase block mb-1">Creation Inspector</span>
          <h3 id="detail-modal-title" class="font-heading font-bold text-lg text-white mb-3">Cybernetic Fluid Portrait</h3>
          <div class="bg-ink-2 p-3 rounded-xl border border-white/5 mb-4">
            <span class="text-[10px] text-slate-9 uppercase font-mono block mb-1 flex items-center gap-1">
              <i class="hgi-stroke hgi-ai-magic text-xs text-brand"></i>
              Prompt:
            </span>
            <p id="detail-modal-prompt" class="text-xs text-slate-12 leading-relaxed">Cinematic 35mm film still of a futuristic neon Kyoto alleyway, rainy reflections, volumetric smoke, high detail</p>
          </div>
          <div class="space-y-1.5 text-xs text-slate-11 font-mono mb-6">
            <div class="flex justify-between"><span>Model:</span> <span id="detail-modal-model" class="text-white">Seedream 5.0</span></div>
            <div class="flex justify-between"><span>Seed:</span> <span class="text-white">83920147</span></div>
            <div class="flex justify-between"><span>CFG Scale:</span> <span class="text-white">7.5</span></div>
            <div class="flex justify-between"><span>Resolution:</span> <span class="text-white">2048 x 2048 (4K)</span></div>
          </div>
        </div>
        <div class="space-y-2 pt-4 border-t border-white/10">
          <button onclick="remixImageAction();" class="w-full py-2.5 rounded-xl bg-brand hover:bg-brand-hover text-white font-bold text-xs transition flex items-center justify-center gap-1.5">
            <i class="hgi-stroke hgi-sparkles text-xs"></i>
            <span>Remix into Studio</span>
          </button>
          <button onclick="closeModal('image-detail'); openModal('upscale');" class="w-full py-2.5 rounded-xl bg-white/10 hover:bg-white/15 text-white font-semibold text-xs transition flex items-center justify-center gap-1.5">
            <i class="hgi-stroke hgi-ai-image-01 text-xs"></i>
            <span>Topaz AI Upscale</span>
          </button>
          <button onclick="closeModal('image-detail'); openModal('export');" class="w-full py-2.5 rounded-xl border border-white/15 text-white hover:bg-white/5 text-xs font-semibold transition flex items-center justify-center gap-1.5">
            <i class="hgi-stroke hgi-download-01 text-xs"></i>
            <span>Download File</span>
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- MODAL 3: CREDITS & SUBSCRIPTION PLANS -->
  <div id="modal-credits" class="oa-modal hidden fixed inset-0 z-[100] bg-black/80 backdrop-blur-md flex items-center justify-center p-4">
    <div class="bg-ink-1 border border-white/15 rounded-3xl w-full max-w-lg p-6 shadow-2xl relative text-left">
      <button onclick="closeModal('credits')" class="absolute right-4 top-4 text-slate-8 hover:text-white transition">
        <i class="hgi-stroke hgi-cancel-01 text-base"></i>
      </button>
      <div class="text-center mb-6">
        <div class="w-10 h-10 rounded-xl bg-accent-emerald/20 text-accent-emerald flex items-center justify-center mx-auto mb-2 text-lg">
          <i class="hgi-stroke hgi-diamond-01 text-xl"></i>
        </div>
        <h3 class="font-heading font-extrabold text-2xl text-white">Instant Credit Packages</h3>
        <p class="text-xs text-slate-11 mt-1">Universal credits for image generation, Veo 3 video, and Topaz upscaling</p>
      </div>
      <div class="space-y-3 mb-6">
        <div onclick="purchaseCredits(1000)" class="p-4 rounded-2xl bg-ink-2 hover:bg-ink-3 border border-white/10 hover:border-brand cursor-pointer flex items-center justify-between transition">
          <div>
            <h4 class="font-bold text-white text-sm flex items-center gap-1.5">
              <i class="hgi-stroke hgi-diamond-01 text-xs text-brand"></i>
              1,000 Credits
            </h4>
            <span class="text-[11px] text-slate-9">Approx. 1,000 image renders or 70 videos</span>
          </div>
          <span class="text-brand font-bold text-base">$10</span>
        </div>
        <div onclick="purchaseCredits(3500)" class="p-4 rounded-2xl bg-ink-2 hover:bg-ink-3 border border-brand/50 cursor-pointer flex items-center justify-between transition relative shadow-lg shadow-brand/10">
          <span class="absolute -top-2.5 right-4 bg-brand text-white text-[9px] font-bold px-2 py-0.5 rounded-full uppercase">Most Popular</span>
          <div>
            <h4 class="font-bold text-white text-sm flex items-center gap-1.5">
              <i class="hgi-stroke hgi-diamond-01 text-xs text-brand"></i>
              3,500 Credits
            </h4>
            <span class="text-[11px] text-slate-9">Includes Topaz 4x Ultra Upscaling</span>
          </div>
          <span class="text-brand font-bold text-base">$25</span>
        </div>
        <div onclick="purchaseCredits(8000)" class="p-4 rounded-2xl bg-ink-2 hover:bg-ink-3 border border-white/10 hover:border-brand cursor-pointer flex items-center justify-between transition">
          <div>
            <h4 class="font-bold text-white text-sm flex items-center gap-1.5">
              <i class="hgi-stroke hgi-diamond-01 text-xs text-brand"></i>
              8,000 Credits
            </h4>
            <span class="text-[11px] text-slate-9">For high-throughput studio video production</span>
          </div>
          <span class="text-brand font-bold text-base">$50</span>
        </div>
      </div>
      <a href="pricing.html" class="block text-center text-xs text-brand hover:underline font-semibold">Or subscribe to a monthly/annual unlimited plan &rsaquo;</a>
    </div>
  </div>

  <!-- MODAL 4: MODEL SELECTOR -->
  <div id="modal-model-selector" class="oa-modal hidden fixed inset-0 z-[100] bg-black/85 backdrop-blur-md flex items-center justify-center p-4">
    <div class="bg-ink-1 border border-white/15 rounded-3xl w-full max-w-2xl p-6 shadow-2xl relative max-h-[85vh] flex flex-col text-left">
      <button onclick="closeModal('model-selector')" class="absolute right-4 top-4 text-slate-8 hover:text-white transition">
        <i class="hgi-stroke hgi-cancel-01 text-base"></i>
      </button>
      <div class="mb-4">
        <h3 class="font-heading font-bold text-xl text-white">Select AI Model</h3>
        <p class="text-xs text-slate-11">Filter by capability, fidelity, and generation latency</p>
      </div>
      <div class="space-y-2.5 overflow-y-auto custom-scrollbar flex-1 pr-1">
        <div onclick="selectModelGlobal('Seedream 5.0 (Latest)')" class="p-3.5 rounded-xl bg-ink-2 hover:bg-ink-3 border border-brand/50 cursor-pointer flex items-center justify-between">
          <div>
            <div class="flex items-center gap-2">
              <i class="hgi-stroke hgi-flash text-brand text-sm"></i>
              <span class="font-bold text-xs text-white">Seedream 5.0</span>
              <span class="bg-brand text-white text-[9px] px-1.5 py-0.2 rounded font-bold">Recommended</span>
            </div>
            <p class="text-[11px] text-slate-9 mt-0.5">Flagship photorealism, typography, fine textural details.</p>
          </div>
          <span class="text-xs font-mono text-slate-11">1 Credit</span>
        </div>
        <div onclick="selectModelGlobal('Google Veo 3 Video')" class="p-3.5 rounded-xl bg-ink-2 hover:bg-ink-3 border border-white/5 cursor-pointer flex items-center justify-between">
          <div>
            <div class="flex items-center gap-2">
              <i class="hgi-stroke hgi-ai-video-01 text-accent-cyan text-sm"></i>
              <span class="font-bold text-xs text-white">Google Veo 3</span>
              <span class="bg-accent-cyan/20 text-accent-cyan text-[9px] px-1.5 py-0.2 rounded font-bold">Video</span>
            </div>
            <p class="text-[11px] text-slate-9 mt-0.5">High definition 1080p cinematic video generation with natural motion physics.</p>
          </div>
          <span class="text-xs font-mono text-slate-11">5 Credits</span>
        </div>
        <div onclick="selectModelGlobal('FLUX.1 Dev')" class="p-3.5 rounded-xl bg-ink-2 hover:bg-ink-3 border border-white/5 cursor-pointer flex items-center justify-between">
          <div>
            <div class="flex items-center gap-2">
              <i class="hgi-stroke hgi-ai-image-01 text-accent-emerald text-sm"></i>
              <span class="font-bold text-xs text-white">FLUX.1 Dev</span>
              <span class="bg-accent-emerald/20 text-accent-emerald text-[9px] px-1.5 py-0.2 rounded font-bold">Open Weights</span>
            </div>
            <p class="text-[11px] text-slate-9 mt-0.5">12B parameter flow transformer model with photorealistic lighting.</p>
          </div>
          <span class="text-xs font-mono text-slate-11">1 Credit</span>
        </div>
        <div onclick="selectModelGlobal('Kling 3.0 Pro')" class="p-3.5 rounded-xl bg-ink-2 hover:bg-ink-3 border border-white/5 cursor-pointer flex items-center justify-between">
          <div>
            <div class="flex items-center gap-2">
              <i class="hgi-stroke hgi-clapperboard text-accent-lime text-sm"></i>
              <span class="font-bold text-xs text-white">Kling 3.0 Pro</span>
              <span class="bg-accent-lime/20 text-accent-lime text-[9px] px-1.5 py-0.2 rounded font-bold">Dynamic</span>
            </div>
            <p class="text-[11px] text-slate-9 mt-0.5">Advanced fluid motion, facial expressions, and complex spatial panning.</p>
          </div>
          <span class="text-xs font-mono text-slate-11">8 Credits</span>
        </div>
      </div>
    </div>
  </div>

  <!-- MODAL 5: STYLE PRESETS -->
  <div id="modal-styles" class="oa-modal hidden fixed inset-0 z-[100] bg-black/85 backdrop-blur-md flex items-center justify-center p-4">
    <div class="bg-ink-1 border border-white/15 rounded-3xl w-full max-w-2xl p-6 shadow-2xl relative max-h-[85vh] flex flex-col text-left">
      <button onclick="closeModal('styles')" class="absolute right-4 top-4 text-slate-8 hover:text-white transition">
        <i class="hgi-stroke hgi-cancel-01 text-base"></i>
      </button>
      <h3 class="font-heading font-bold text-xl text-white mb-1">Visual Art Styles</h3>
      <p class="text-xs text-slate-11 mb-4">Click to inject lighting, texture, and aesthetic tokens</p>
      <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 overflow-y-auto custom-scrollbar flex-1 pr-1">
        <div onclick="selectStyleGlobal('Cinematic Photorealism')" class="p-3 rounded-xl bg-ink-2 hover:border-brand border border-white/5 cursor-pointer text-xs">
          <i class="hgi-stroke hgi-camera-01 text-2xl text-brand block mb-2"></i>
          <span class="font-bold text-white block">Cinematic 35mm</span>
          <span class="text-[10px] text-slate-9">Kodak Portra, depth of field</span>
        </div>
        <div onclick="selectStyleGlobal('Makoto Shinkai Anime')" class="p-3 rounded-xl bg-ink-2 hover:border-brand border border-white/5 cursor-pointer text-xs">
          <i class="hgi-stroke hgi-sparkles text-2xl text-pink-400 block mb-2"></i>
          <span class="font-bold text-white block">Anime Film</span>
          <span class="text-[10px] text-slate-9">Luminous clouds, vibrant sky</span>
        </div>
        <div onclick="selectStyleGlobal('Cyberpunk Neon')" class="p-3 rounded-xl bg-ink-2 hover:border-brand border border-white/5 cursor-pointer text-xs">
          <i class="hgi-stroke hgi-flash text-2xl text-accent-cyan block mb-2"></i>
          <span class="font-bold text-white block">Cyberpunk 2077</span>
          <span class="text-[10px] text-slate-9">Holograms, wet pavement</span>
        </div>
        <div onclick="selectStyleGlobal('Pixar 3D Animation')" class="p-3 rounded-xl bg-ink-2 hover:border-brand border border-white/5 cursor-pointer text-xs">
          <i class="hgi-stroke hgi-cube text-2xl text-blue-400 block mb-2"></i>
          <span class="font-bold text-white block">3D Animated CGI</span>
          <span class="text-[10px] text-slate-9">Subsurface scattering clay</span>
        </div>
        <div onclick="selectStyleGlobal('Classic Oil Painting')" class="p-3 rounded-xl bg-ink-2 hover:border-brand border border-white/5 cursor-pointer text-xs">
          <i class="hgi-stroke hgi-ai-paintbrush text-2xl text-amber-400 block mb-2"></i>
          <span class="font-bold text-white block">Impressionist Oil</span>
          <span class="text-[10px] text-slate-9">Textured impasto brushwork</span>
        </div>
        <div onclick="selectStyleGlobal('Retro Vaporwave')" class="p-3 rounded-xl bg-ink-2 hover:border-brand border border-white/5 cursor-pointer text-xs">
          <i class="hgi-stroke hgi-clapperboard text-2xl text-purple-400 block mb-2"></i>
          <span class="font-bold text-white block">VHS Vaporwave</span>
          <span class="text-[10px] text-slate-9">Chromatic aberration 80s</span>
        </div>
      </div>
    </div>
  </div>

  <!-- MODAL 6: 3D CAMERA & MOTION -->
  <div id="modal-camera" class="oa-modal hidden fixed inset-0 z-[100] bg-black/85 backdrop-blur-md flex items-center justify-center p-4">
    <div class="bg-ink-1 border border-white/15 rounded-3xl w-full max-w-lg p-6 shadow-2xl relative text-left">
      <button onclick="closeModal('camera')" class="absolute right-4 top-4 text-slate-8 hover:text-white transition">
        <i class="hgi-stroke hgi-cancel-01 text-base"></i>
      </button>
      <h3 class="font-heading font-bold text-xl text-white mb-1">3D Motion & Gimbal Controller</h3>
      <p class="text-xs text-slate-11 mb-6">Set camera motion paths for video generation</p>
      <div class="grid grid-cols-3 gap-2 mb-6">
        <button onclick="setCameraPathAction('Pan Left')" class="p-3 bg-ink-2 hover:bg-brand/20 border border-white/10 hover:border-brand rounded-xl text-center text-xs font-semibold text-white flex flex-col items-center gap-1">
          <i class="hgi-stroke hgi-arrow-left-01 text-base"></i>
          <span>Pan Left</span>
        </button>
        <button onclick="setCameraPathAction('Zoom In')" class="p-3 bg-ink-2 hover:bg-brand/20 border border-white/10 hover:border-brand rounded-xl text-center text-xs font-semibold text-white flex flex-col items-center gap-1">
          <i class="hgi-stroke hgi-search-01 text-base"></i>
          <span>Zoom In</span>
        </button>
        <button onclick="setCameraPathAction('Pan Right')" class="p-3 bg-ink-2 hover:bg-brand/20 border border-white/10 hover:border-brand rounded-xl text-center text-xs font-semibold text-white flex flex-col items-center gap-1">
          <i class="hgi-stroke hgi-arrow-right-01 text-base"></i>
          <span>Pan Right</span>
        </button>
        <button onclick="setCameraPathAction('Tilt Up')" class="p-3 bg-ink-2 hover:bg-brand/20 border border-white/10 hover:border-brand rounded-xl text-center text-xs font-semibold text-white flex flex-col items-center gap-1">
          <i class="hgi-stroke hgi-arrow-up-01 text-base"></i>
          <span>Tilt Up</span>
        </button>
        <button onclick="setCameraPathAction('Orbit 360')" class="p-3 bg-ink-2 hover:bg-brand/20 border border-white/10 hover:border-brand rounded-xl text-center text-xs font-semibold text-white flex flex-col items-center gap-1">
          <i class="hgi-stroke hgi-refresh text-base"></i>
          <span>Orbit 360</span>
        </button>
        <button onclick="setCameraPathAction('Tilt Down')" class="p-3 bg-ink-2 hover:bg-brand/20 border border-white/10 hover:border-brand rounded-xl text-center text-xs font-semibold text-white flex flex-col items-center gap-1">
          <i class="hgi-stroke hgi-arrow-down-01 text-base"></i>
          <span>Tilt Down</span>
        </button>
      </div>
      <button onclick="closeModal('camera')" class="w-full py-2.5 rounded-xl bg-brand text-white font-bold text-xs flex items-center justify-center gap-1.5">
        <i class="hgi-stroke hgi-checkmark-circle-01 text-xs"></i>
        <span>Apply Camera Trajectory</span>
      </button>
    </div>
  </div>

  <!-- MODAL 7: TOPAZ AI UPSCALE -->
  <div id="modal-upscale" class="oa-modal hidden fixed inset-0 z-[100] bg-black/85 backdrop-blur-md flex items-center justify-center p-4">
    <div class="bg-ink-1 border border-white/15 rounded-3xl w-full max-w-xl p-6 shadow-2xl relative text-left">
      <button onclick="closeModal('upscale')" class="absolute right-4 top-4 text-slate-8 hover:text-white transition">
        <i class="hgi-stroke hgi-cancel-01 text-base"></i>
      </button>
      <h3 class="font-heading font-bold text-xl text-white mb-1">Topaz Lab AI Enhancer</h3>
      <p class="text-xs text-slate-11 mb-6">Multiply resolution while reconstructing skin, textures, and sharp edges</p>
      <div class="space-y-4 mb-6">
        <div>
          <label class="text-xs text-slate-11 block mb-2 font-mono">Upscaling Multiplier</label>
          <div class="grid grid-cols-3 gap-2 text-xs font-bold text-white">
            <button class="py-2.5 rounded-xl bg-ink-2 border border-white/10 hover:border-brand">2x (1080p)</button>
            <button class="py-2.5 rounded-xl bg-brand/20 border border-brand text-brand">4x Ultra (4K UHD)</button>
            <button class="py-2.5 rounded-xl bg-ink-2 border border-white/10 hover:border-brand">8x Master (8K)</button>
          </div>
        </div>
        <div class="flex items-center justify-between p-3 rounded-xl bg-ink-2 border border-white/5 text-xs">
          <div>
            <span class="text-white font-semibold block">Face Restoration Engine</span>
            <span class="text-[10px] text-slate-9">Rebuilds realistic eye reflections & pores</span>
          </div>
          <input type="checkbox" checked class="accent-brand w-4 h-4">
        </div>
      </div>
      <button onclick="executeUpscaleAction()" class="w-full py-3 rounded-xl bg-brand hover:bg-brand-hover text-white font-bold text-xs transition shadow-lg shadow-brand/20 flex items-center justify-center gap-1.5">
        <i class="hgi-stroke hgi-sparkles text-xs"></i>
        <span>Enhance Image (2 Credits)</span>
      </button>
    </div>
  </div>

  <!-- MODAL 8: EXPORT & RESOLUTION -->
  <div id="modal-export" class="oa-modal hidden fixed inset-0 z-[100] bg-black/85 backdrop-blur-md flex items-center justify-center p-4">
    <div class="bg-ink-1 border border-white/15 rounded-3xl w-full max-w-md p-6 shadow-2xl relative text-left">
      <button onclick="closeModal('export')" class="absolute right-4 top-4 text-slate-8 hover:text-white transition">
        <i class="hgi-stroke hgi-cancel-01 text-base"></i>
      </button>
      <h3 class="font-heading font-bold text-xl text-white mb-1">Export Media</h3>
      <p class="text-xs text-slate-11 mb-5">Select container format and compression settings</p>
      <div class="space-y-3 mb-6 text-xs">
        <div>
          <label class="text-slate-11 font-mono block mb-1.5">File Format</label>
          <div class="grid grid-cols-4 gap-2 font-semibold">
            <button class="py-2 rounded-lg bg-brand/20 border border-brand text-white">PNG</button>
            <button class="py-2 rounded-lg bg-ink-2 border border-white/5 text-slate-11">WebP</button>
            <button class="py-2 rounded-lg bg-ink-2 border border-white/5 text-slate-11">MP4</button>
            <button class="py-2 rounded-lg bg-ink-2 border border-white/5 text-slate-11">ProRes</button>
          </div>
        </div>
        <div class="flex items-center justify-between p-3 rounded-xl bg-ink-2 border border-white/5">
          <span class="text-white">Embed Prompt & Metadata</span>
          <input type="checkbox" checked class="accent-brand w-4 h-4">
        </div>
      </div>
      <button onclick="alert('Export ready! Downloading high-res asset...'); closeModal('export');" class="w-full py-2.5 rounded-xl bg-brand hover:bg-brand-hover text-white font-bold text-xs transition flex items-center justify-center gap-1.5">
        <i class="hgi-stroke hgi-download-01 text-xs"></i>
        <span>Download File</span>
      </button>
    </div>
  </div>

  <!-- MODAL 9: STUDIO KEYBOARD SHORTCUTS -->
  <div id="modal-shortcuts" class="oa-modal hidden fixed inset-0 z-[100] bg-black/85 backdrop-blur-md flex items-center justify-center p-4">
    <div class="bg-ink-1 border border-white/15 rounded-3xl w-full max-w-md p-6 shadow-2xl relative text-left">
      <button onclick="closeModal('shortcuts')" class="absolute right-4 top-4 text-slate-8 hover:text-white transition">
        <i class="hgi-stroke hgi-cancel-01 text-base"></i>
      </button>
      <h3 class="font-heading font-bold text-xl text-white mb-1 flex items-center gap-2">
        <i class="hgi-stroke hgi-keyboard text-lg text-brand"></i>
        <span>Keyboard Shortcuts</span>
      </h3>
      <p class="text-xs text-slate-11 mb-6">Accelerate your generative workflow</p>
      <div class="space-y-2.5 text-xs">
        <div class="flex justify-between items-center py-1.5 border-b border-white/5">
          <span class="text-slate-11">Trigger Generation</span>
          <kbd class="px-2 py-0.5 rounded bg-ink-2 border border-white/15 font-mono text-white text-[11px]">Cmd + Enter</kbd>
        </div>
        <div class="flex justify-between items-center py-1.5 border-b border-white/5">
          <span class="text-slate-11">Focus Prompt Box</span>
          <kbd class="px-2 py-0.5 rounded bg-ink-2 border border-white/15 font-mono text-white text-[11px]">/</kbd>
        </div>
        <div class="flex justify-between items-center py-1.5 border-b border-white/5">
          <span class="text-slate-11">Dismiss Active Modal</span>
          <kbd class="px-2 py-0.5 rounded bg-ink-2 border border-white/15 font-mono text-white text-[11px]">Escape</kbd>
        </div>
        <div class="flex justify-between items-center py-1.5">
          <span class="text-slate-11">Open Shortcuts Cheatsheet</span>
          <kbd class="px-2 py-0.5 rounded bg-ink-2 border border-white/15 font-mono text-white text-[11px]">?</kbd>
        </div>
      </div>
    </div>
  </div>
  `;

  function injectModals() {
    if (!document.getElementById('openart-modals-container')) {
      const div = document.createElement('div');
      div.id = 'openart-modals-container';
      div.innerHTML = modalTemplates;
      document.body.appendChild(div);

      document.querySelectorAll('.oa-modal').forEach(m => {
        m.addEventListener('click', function(e) {
          if (e.target === this) {
            this.classList.add('hidden');
          }
        });
      });
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', injectModals);
  } else {
    injectModals();
  }

  window.openModal = function(id) {
    const el = document.getElementById('modal-' + id);
    if (el) el.classList.remove('hidden');
  };

  window.closeModal = function(id) {
    const el = document.getElementById('modal-' + id);
    if (el) el.classList.add('hidden');
  };

  window.purchaseCredits = function(amt) {
    window.OpenArtState.credits += amt;
    localStorage.setItem('openart_credits', window.OpenArtState.credits);
    updateCreditDisplays();
    alert(`Successfully added ${amt} credits! Current balance: ${window.OpenArtState.credits} credits.`);
    closeModal('credits');
  };

  window.simulateLogin = function(provider) {
    window.OpenArtState.userLoggedIn = true;
    localStorage.setItem('openart_logged_in', 'true');
    alert(`Signed in successfully with ${provider}!`);
    closeModal('auth');
    if (!window.location.pathname.includes('home.html') && !window.location.pathname.includes('create-')) {
      window.location.href = 'home.html';
    }
  };

  window.selectModelGlobal = function(name) {
    window.OpenArtState.activeModel = name;
    localStorage.setItem('openart_model', name);
    const label = document.getElementById('active-model-name');
    if (label) label.innerText = name;
    closeModal('model-selector');
  };

  window.selectStyleGlobal = function(style) {
    window.OpenArtState.activeStyle = style;
    const promptInput = document.getElementById('studio-prompt-input');
    if (promptInput) {
      promptInput.value = promptInput.value + `, style of ${style}`;
    }
    closeModal('styles');
  };

  window.setCameraPathAction = function(path) {
    alert(`Applied camera trajectory: ${path}`);
    closeModal('camera');
  };

  window.executeUpscaleAction = function() {
    alert('Topaz AI Upscaling 4x initiated! Detail reconstructed.');
    closeModal('upscale');
  };

  window.remixImageAction = function() {
    window.location.href = 'create-image.html?prompt=' + encodeURIComponent('Remix variation of cyber geisha in golden baroque lighting, 8k');
  };

  function updateCreditDisplays() {
    document.querySelectorAll('.credit-balance-display').forEach(el => {
      el.innerText = `${window.OpenArtState.credits} Credits`;
    });
  }

  window.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
      document.querySelectorAll('.oa-modal').forEach(m => m.classList.add('hidden'));
    }
    if (e.key === '?' && !['INPUT', 'TEXTAREA'].includes(document.activeElement.tagName)) {
      openModal('shortcuts');
    }
  });

})();

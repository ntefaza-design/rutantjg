/* =========================================
   RUTAN TANJUNG — JAVASCRIPT
   ========================================= */

/* ===== NAVBAR SCROLL EFFECT ===== */
const navbar = document.getElementById('navbar');
const hamburger = document.getElementById('hamburger');
const navLinks = document.getElementById('navLinks');

if (navbar) {
  window.addEventListener('scroll', () => {
    if (window.scrollY > 50) {
      navbar.classList.add('scrolled');
    } else {
      navbar.classList.remove('scrolled');
    }
  }, { passive: true });
}

/* ===== HAMBURGER MENU & DRAWER ===== */
function closeMobileNav() {
  if (!navLinks || !hamburger) return;
  navLinks.classList.remove('open');
  hamburger.setAttribute('aria-expanded', 'false');
  const spans = hamburger.querySelectorAll('span');
  if (spans.length >= 3) {
    spans[0].style.transform = '';
    spans[1].style.opacity = '';
    spans[2].style.transform = '';
  }
}

function toggleMobileNav() {
  if (!navLinks || !hamburger) return;
  const isOpen = navLinks.classList.toggle('open');
  hamburger.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
  const spans = hamburger.querySelectorAll('span');
  if (spans.length >= 3) {
    if (isOpen) {
      spans[0].style.transform = 'rotate(45deg) translate(5px, 5px)';
      spans[1].style.opacity = '0';
      spans[2].style.transform = 'rotate(-45deg) translate(5px, -5px)';
    } else {
      spans[0].style.transform = '';
      spans[1].style.opacity = '';
      spans[2].style.transform = '';
    }
  }
}

if (hamburger) {
  hamburger.setAttribute('aria-expanded', 'false');
  hamburger.addEventListener('click', (e) => {
    e.stopPropagation();
    toggleMobileNav();
  });
}

// Close nav when clicking outside navbar
document.addEventListener('click', (e) => {
  if (navbar && !navbar.contains(e.target)) {
    closeMobileNav();
  }
});

// Close nav on Escape key
document.addEventListener('keydown', (e) => {
  if (e.key === 'Escape') {
    closeMobileNav();
  }
});

// Close nav when any link inside navLinks is clicked
if (navLinks) {
  navLinks.querySelectorAll('a').forEach(link => {
    link.addEventListener('click', () => {
      closeMobileNav();
    });
  });
}

// Auto close drawer when resizing past tablet breakpoint (e.g. desktop)
window.addEventListener('resize', () => {
  if (window.innerWidth > 1024) {
    closeMobileNav();
  }
});

/* ===== PARTICLES ===== */
function createParticles() {
  const container = document.getElementById('particles');
  if (!container) return;
  const count = 20;
  for (let i = 0; i < count; i++) {
    const p = document.createElement('div');
    p.classList.add('particle');
    const size = Math.random() * 8 + 3;
    p.style.cssText = `
      width: ${size}px;
      height: ${size}px;
      left: ${Math.random() * 100}%;
      top: ${Math.random() * 100}%;
      animation-delay: ${Math.random() * 8}s;
      animation-duration: ${Math.random() * 6 + 6}s;
      opacity: ${Math.random() * 0.4 + 0.1};
    `;
    container.appendChild(p);
  }
}
createParticles();

/* ===== SCROLL REVEAL ===== */
function setupReveal() {
  const revealEls = document.querySelectorAll(
    '.about-grid, .vm-card, .pejabat-ketua-card, .pejabat-card, .jadwal-card, .kontak-item, .sosmed-card, .pengaduan-info, .pengaduan-form, .section-header, .sosmed-banner-card, .ig-post-card, .sosmed-channel-card'
  );

  revealEls.forEach(el => {
    el.classList.add('reveal');
  });

  const observer = new IntersectionObserver((entries) => {
    entries.forEach((entry, i) => {
      if (entry.isIntersecting) {
        setTimeout(() => {
          entry.target.classList.add('visible');
        }, 80);
        observer.unobserve(entry.target);
      }
    });
  }, {
    threshold: 0.12,
    rootMargin: '0px 0px -40px 0px'
  });

  revealEls.forEach(el => observer.observe(el));
}
setupReveal();

/* ===== ACTIVE NAV LINK & SCROLL SPY ===== */
const trackedSectionIds = ['tentang', 'visi-misi', 'pejabat', 'jadwal', 'pengaduan', 'sosmed', 'kontak'];
const navLinkEls = document.querySelectorAll('.nav-link');
let isManualScroll = false;
let manualScrollTimer = null;

function setActiveNavLink(targetId) {
  navLinkEls.forEach(link => {
    link.style.color = '';
    link.style.background = '';
    if (targetId && link.getAttribute('href') === `#${targetId}`) {
      link.classList.add('active');
    } else {
      link.classList.remove('active');
    }
  });
}

// Langsung beri efek aktif saat link navigasi / tombol anchor diklik
document.querySelectorAll('a[href^="#"]').forEach(anchor => {
  anchor.addEventListener('click', () => {
    const targetId = anchor.getAttribute('href').substring(1);
    if (trackedSectionIds.includes(targetId)) {
      setActiveNavLink(targetId);
      isManualScroll = true;
      clearTimeout(manualScrollTimer);
      manualScrollTimer = setTimeout(() => {
        isManualScroll = false;
      }, 900);
    }
  });
});

// Update highlight secara mulus saat halaman digulir (Scroll Spy)
function updateScrollSpy() {
  if (isManualScroll) return;

  const scrollY = window.scrollY;
  const navbarHeight = navbar ? navbar.offsetHeight : 80;
  const offset = navbarHeight + 80;

  // Jika scroll sudah mendekati bagian terbawah dokumen
  if ((window.innerHeight + scrollY) >= document.documentElement.scrollHeight - 60) {
    setActiveNavLink('kontak');
    return;
  }

  // Jika masih di bagian Hero paling atas
  const tentangEl = document.getElementById('tentang');
  if (tentangEl && (scrollY + offset) < tentangEl.offsetTop) {
    setActiveNavLink(null);
    return;
  }

  // Cari section terakhir yang posisinya telah dilalui scroll
  let currentActiveId = null;
  trackedSectionIds.forEach(id => {
    const section = document.getElementById(id);
    if (section) {
      const top = section.offsetTop - offset;
      if (scrollY >= top) {
        currentActiveId = id;
      }
    }
  });

  if (currentActiveId) {
    setActiveNavLink(currentActiveId);
  }
}

window.addEventListener('scroll', updateScrollSpy, { passive: true });

// Inisialisasi saat pertama kali dimuat atau saat URL memiliki hash (#)
function initNavHighlight() {
  if (window.location.hash) {
    const hashId = window.location.hash.substring(1);
    if (trackedSectionIds.includes(hashId)) {
      setActiveNavLink(hashId);
      return;
    }
  }
  updateScrollSpy();
}

initNavHighlight();
window.addEventListener('load', initNavHighlight);
window.addEventListener('hashchange', initNavHighlight);

/* ===== PENGADUAN FORM (Legacy — tidak digunakan, pengaduan via WA) ===== */
// Fungsi generateTicket, submitPengaduan, closeModal dipertahankan untuk kompatibilitas
// namun form pengaduan telah digantikan sepenuhnya oleh integrasi WhatsApp.
function generateTicket() {
  const year = new Date().getFullYear();
  const num = Math.floor(Math.random() * 90000) + 10000;
  return `RTN-${year}-${num}`;
}

function closeModal() {
  const modal = document.getElementById('modalOverlay');
  if (modal) modal.classList.remove('active');
}

// Close modal on overlay click (safe guard)
const modalOverlayEl = document.getElementById('modalOverlay');
if (modalOverlayEl) {
  modalOverlayEl.addEventListener('click', (e) => {
    if (e.target === e.currentTarget) closeModal();
  });
}

/* ===== FILE UPLOAD UI (Legacy — form pengaduan digantikan WA) ===== */
const fileInput = document.getElementById('lampiran');
if (fileInput) {
  fileInput.addEventListener('change', () => {
    const file = fileInput.files[0];
    const ui = document.querySelector('.file-upload-ui span');
    if (ui) {
      ui.textContent = file
        ? `✅ ${file.name} (${(file.size / 1024 / 1024).toFixed(2)} MB)`
        : 'Klik atau seret file ke sini (PDF, JPG, PNG — maks 5MB)';
    }
  });
}

/* ===== SMOOTH COUNTER ANIMATION ===== */
function animateCounters() {
  const counters = document.querySelectorAll('.stat-number');
  counters.forEach(counter => {
    const target = counter.textContent;
    // Only animate pure numbers
    if (/^\d+$/.test(target)) {
      const end = parseInt(target);
      let current = 0;
      const increment = Math.ceil(end / 30);
      const timer = setInterval(() => {
        current += increment;
        if (current >= end) {
          counter.textContent = end;
          clearInterval(timer);
        } else {
          counter.textContent = current;
        }
      }, 50);
    }
  });
}

// Trigger counter animation when hero is in view
const heroObserver = new IntersectionObserver((entries) => {
  entries.forEach(entry => {
    if (entry.isIntersecting) {
      setTimeout(animateCounters, 800);
      heroObserver.unobserve(entry.target);
    }
  });
}, { threshold: 0.5 });

const heroStats = document.querySelector('.hero-stats');
if (heroStats) heroObserver.observe(heroStats);

/* ===== SCHEDULE TAB HOVER HIGHLIGHT ===== */
document.querySelectorAll('.schedule-row:not(.closed)').forEach(row => {
  row.addEventListener('mouseenter', () => {
    row.style.background = 'rgba(10,36,99,0.03)';
    row.style.borderRadius = '8px';
    row.style.transition = 'background 0.3s ease';
  });
  row.addEventListener('mouseleave', () => {
    row.style.background = '';
  });
});

/* ===== GOLD GLOW ON SCROLL FOR STATS ===== */
window.addEventListener('scroll', () => {
  const scrollPercent = window.scrollY / (document.body.scrollHeight - window.innerHeight);

  // Subtle parallax for hero
  const hero = document.querySelector('.hero-content');
  if (hero && window.scrollY < window.innerHeight) {
    hero.style.transform = `translateY(${window.scrollY * 0.15}px)`;
  }
});

/* ===== KONTAK ITEM HOVER EFFECT ===== */
document.querySelectorAll('.kontak-item').forEach(item => {
  item.addEventListener('mouseenter', () => {
    const icon = item.querySelector('.kontak-icon');
    if (icon) {
      icon.style.background = 'linear-gradient(135deg, var(--primary), var(--primary-light))';
      icon.style.color = 'white';
      icon.style.transform = 'scale(1.1)';
      icon.style.transition = 'all 0.3s ease';
    }
  });
  item.addEventListener('mouseleave', () => {
    const icon = item.querySelector('.kontak-icon');
    if (icon) {
      icon.style.background = '';
      icon.style.color = '';
      icon.style.transform = '';
    }
  });
});

/* ===== YEAR IN FOOTER ===== */
const footerYearEl = document.getElementById('footer-year');
if (footerYearEl) {
  footerYearEl.textContent = new Date().getFullYear();
}
// Fallback: update teks lama jika ada
const yearEls = document.querySelectorAll('.footer-bottom p');
if (yearEls.length > 0) {
  const currentYear = new Date().getFullYear();
  yearEls[0].textContent = yearEls[0].textContent.replace(/\d{4}/, currentYear);
}

/* ===== REALTIME SERVICE STATUS BADGE ===== */
/**
 * Jadwal Kunjungan   : Senin–Kamis 08.30–11.30 WITA
 * Jadwal Penitipan   : Senin–Kamis 08.30–11.30 & 14.00–16.00 WITA
 *                      Jumat & Sabtu 08.30–11.30 WITA
 */
function getWITADateTime() {
  try {
    const now = new Date();
    const formatter = new Intl.DateTimeFormat('id-ID', {
      timeZone: 'Asia/Makassar',
      weekday: 'short',
      hour: 'numeric',
      minute: 'numeric',
      hour12: false
    });
    const parts = formatter.formatToParts(now);
    const dayStr = parts.find(p => p.type === 'weekday')?.value?.toLowerCase() || '';
    const h = parseInt(parts.find(p => p.type === 'hour')?.value || '0', 10);
    const m = parseInt(parts.find(p => p.type === 'minute')?.value || '0', 10);
    const timeVal = h + m / 60;
    // Map Indonesian short weekday -> 0 Sun, 1 Mon... 6 Sat
    const dayMap = { 'min': 0, 'sen': 1, 'sel': 2, 'rab': 3, 'kam': 4, 'jum': 5, 'sab': 6 };
    const dayNum = dayMap[dayStr.substring(0, 3)] ?? -1;
    return { dayNum, timeVal };
  } catch (e) {
    const now = new Date();
    // Fallback: estimate WITA (UTC+8)
    const wita = new Date(now.getTime() + 8 * 3600 * 1000);
    return { dayNum: wita.getUTCDay(), timeVal: wita.getUTCHours() + wita.getUTCMinutes() / 60 };
  }
}

function isKunjunganOpen() {
  const { dayNum, timeVal } = getWITADateTime();
  // Senin(1)–Kamis(4) jam 08:30–11:30
  if (dayNum >= 1 && dayNum <= 4 && timeVal >= 8.5 && timeVal < 11.5) return true;
  return false;
}

function isPenitipanOpen() {
  const { dayNum, timeVal } = getWITADateTime();
  // Senin(1)–Kamis(4): 08:30–11:30 dan 14:00–16:00
  if (dayNum >= 1 && dayNum <= 4) {
    if (timeVal >= 8.5 && timeVal < 11.5) return true;
    if (timeVal >= 14.0 && timeVal < 16.0) return true;
  }
  // Jumat(5) & Sabtu(6): 08:30–11:30
  if ((dayNum === 5 || dayNum === 6) && timeVal >= 8.5 && timeVal < 11.5) return true;
  return false;
}

function renderServiceBadge(elementId, isOpen, openText, closedText) {
  const el = document.getElementById(elementId);
  if (!el) return;
  if (isOpen) {
    el.className = 'service-status-badge status-open';
    el.innerHTML = `<span class="status-dot"></span>${openText}`;
  } else {
    el.className = 'service-status-badge status-closed';
    el.innerHTML = `<span class="status-dot"></span>${closedText}`;
  }
}

function updateServiceStatus() {
  renderServiceBadge(
    'status-kunjungan',
    isKunjunganOpen(),
    'Kunjungan Sedang Buka',
    'Kunjungan Sedang Tutup'
  );
  renderServiceBadge(
    'status-penitipan',
    isPenitipanOpen(),
    'Penitipan Sedang Buka',
    'Penitipan Sedang Tutup'
  );
}

// Jalankan saat load dan update setiap menit
updateServiceStatus();
setInterval(updateServiceStatus, 60 * 1000);



// Nomor WA resmi Rutan Tanjung
const WA_NUMBER = '6281241974185'; // Format: 62xxxxxxxxxx (081241974185)

// Fungsi menentukan salam otomatis berdasarkan waktu WITA (UTC+8)
function getSalamWITA() {
  try {
    const formatter = new Intl.DateTimeFormat('id-ID', {
      timeZone: 'Asia/Makassar',
      hour: 'numeric',
      minute: 'numeric',
      hour12: false
    });
    const parts = formatter.formatToParts(new Date());
    const h = parseInt(parts.find(p => p.type === 'hour')?.value || '0', 10);
    const m = parseInt(parts.find(p => p.type === 'minute')?.value || '0', 10);
    const timeVal = h + (m / 60);

    if (timeVal >= 4 && timeVal < 11.5) {
      return 'Selamat Pagi';
    } else if (timeVal >= 11.5 && timeVal < 15) {
      return 'Selamat Siang';
    } else if (timeVal >= 15 && timeVal < 18.5) {
      return 'Selamat Sore';
    } else {
      return 'Selamat Malam';
    }
  } catch (e) {
    const witaHour = (new Date().getUTCHours() + 8) % 24;
    if (witaHour >= 4 && witaHour < 11.5) {
      return 'Selamat Pagi';
    } else if (witaHour >= 11.5 && witaHour < 15) {
      return 'Selamat Siang';
    } else if (witaHour >= 15 && witaHour < 18.5) {
      return 'Selamat Sore';
    } else {
      return 'Selamat Malam';
    }
  }
}

function openWA(kategori = '') {
  const salam = getSalamWITA();
  const topik = kategori ? `*${kategori}*` : 'pengaduan';
  const pesan = encodeURIComponent(
    `${salam},\n\nSaya ingin menyampaikan ${topik} kepada Rutan Tanjung Kelas IIB.\n\nNama     : \nNo. KTP  : \nUraian   : \n\nTerima kasih.`
  );
  const url = `https://wa.me/${WA_NUMBER}?text=${pesan}`;
  window.open(url, '_blank', 'noopener,noreferrer');
}

// Update semua link tombol WA utama
function updateWAMainButtons() {
  const salam = getSalamWITA();
  document.querySelectorAll('.btn-wa-main').forEach(btn => {
    const pesan = encodeURIComponent(
      `${salam},\n\nSaya ingin menyampaikan pengaduan kepada Rutan Tanjung Kelas IIB.\n\nNama           : \nJenis Pengaduan: \nUraian         : \n\nTerima kasih.`
    );
    btn.href = `https://wa.me/${WA_NUMBER}?text=${pesan}`;
  });
}

// Inisialisasi dan refresh link saat diklik
updateWAMainButtons();
document.querySelectorAll('.btn-wa-main').forEach(btn => {
  btn.addEventListener('click', updateWAMainButtons);
});



/* ===== FLOATING WA BUTTON ===== */
// Tambah floating WhatsApp button di pojok kanan bawah
function addFloatingWA() {
  const salam = getSalamWITA();
  const floatBtn = document.createElement('a');
  floatBtn.id = 'floating-wa-btn';
  floatBtn.href = `https://wa.me/${WA_NUMBER}?text=${encodeURIComponent(`${salam}, saya ingin menghubungi Rutan Tanjung Kelas IIB.`)}`;
  floatBtn.target = '_blank';
  floatBtn.rel = 'noopener noreferrer';
  floatBtn.setAttribute('aria-label', 'Hubungi via WhatsApp');
  floatBtn.innerHTML = `
    <svg viewBox="0 0 24 24" fill="currentColor" width="28" height="28">
      <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z"/>
      <path d="M12.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 0 0 5.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 0 0-3.48-8.413A11.824 11.824 0 0 0 12.05 0zm0 21.785h-.004a9.87 9.87 0 0 1-5.031-1.378l-.361-.214-3.741.981.998-3.648-.235-.374a9.86 9.86 0 0 1-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 0 1 2.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884z"/>
    </svg>
    <span class="float-wa-tooltip">Pengaduan WA</span>
  `;

  // Styling inline
  floatBtn.style.cssText = `
    position: fixed;
    bottom: 28px;
    right: 28px;
    z-index: 1500;
    width: 60px;
    height: 60px;
    background: linear-gradient(135deg, #25d366, #128c7e);
    color: white;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 6px 25px rgba(37,211,102,0.5);
    transition: all 0.3s cubic-bezier(0.4,0,0.2,1);
    animation: floatWAPulse 2.5s ease-in-out infinite;
    text-decoration: none;
  `;

  const tooltipStyle = document.createElement('style');
  tooltipStyle.textContent = `
    @keyframes floatWAPulse {
      0%, 100% { box-shadow: 0 6px 25px rgba(37,211,102,0.5), 0 0 0 0 rgba(37,211,102,0.4); }
      50% { box-shadow: 0 8px 35px rgba(37,211,102,0.6), 0 0 0 10px rgba(37,211,102,0); }
    }
    #floating-wa-btn:hover {
      transform: scale(1.12) translateY(-3px);
      animation: none;
      box-shadow: 0 12px 40px rgba(37,211,102,0.65);
    }
    #floating-wa-btn:hover .float-wa-tooltip { opacity: 1; transform: translateX(0); }
    .float-wa-tooltip {
      position: absolute;
      right: 68px;
      background: #128c7e;
      color: white;
      font-size: 12px;
      font-weight: 700;
      padding: 6px 12px;
      border-radius: 20px;
      white-space: nowrap;
      opacity: 0;
      transform: translateX(8px);
      transition: all 0.3s ease;
      font-family: 'Plus Jakarta Sans', sans-serif;
      pointer-events: none;
    }
  `;
  document.head.appendChild(tooltipStyle);
  document.body.appendChild(floatBtn);
}

// Tampilkan floating button setelah scroll sedikit
window.addEventListener('scroll', function showFloatWA() {
  if (window.scrollY > 200) {
    addFloatingWA();
    window.removeEventListener('scroll', showFloatWA);
  }
}, { passive: true });

console.log('%c🏛️ Rutan Tanjung Kelas IIB', 'color:#c9a84c;font-size:18px;font-weight:bold;');
console.log('%cWebsite Resmi Rumah Tahanan Negara Tanjung', 'color:#0a2463;font-size:12px;');
console.log('%c📲 WA Pengaduan aktif — Ubah WA_NUMBER di script.js', 'color:#25d366;font-size:11px;');



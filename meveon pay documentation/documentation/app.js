// ================================
// MEVONPAY API Documentation SPA
// Main Application Logic
// ================================

(function() {
  'use strict';

  // ========== State Management ==========
  const state = {
    currentPage: 'home',
    sidebarOpen: false,
    darkMode: false
  };

  // ========== DOM Elements ==========
  const elements = {
    sidebar: document.getElementById('sidebar'),
    overlay: document.getElementById('overlay'),
    menuToggle: document.getElementById('menuToggle'),
    sidebarClose: document.getElementById('sidebarClose'),
    themeToggle: document.getElementById('themeToggle'),
    searchInput: document.getElementById('searchInput'),
    pageContent: document.getElementById('pageContent'),
    navLinks: document.querySelectorAll('.nav-link')
  };

  // ========== Initialize Application ==========
  function init() {
    loadThemePreference();
    setupEventListeners();
    handleInitialRoute();
    setupSearch();
    
    // Disable browser auto-scroll restoration
    if ('scrollRestoration' in history) {
      history.scrollRestoration = 'manual';
    }
  }

  // ========== Theme Management ==========
  function loadThemePreference() {
    const savedTheme = localStorage.getItem('darkMode');
    if (savedTheme === 'enabled') {
      state.darkMode = true;
      document.body.classList.add('dark-mode');
      updateThemeIcon();
    }
  }

  function toggleTheme() {
    state.darkMode = !state.darkMode;
    document.body.classList.toggle('dark-mode');
    
    localStorage.setItem('darkMode', state.darkMode ? 'enabled' : 'disabled');
    updateThemeIcon();
  }

  function updateThemeIcon() {
    const icon = elements.themeToggle.querySelector('i');
    icon.className = state.darkMode ? 'fas fa-sun' : 'fas fa-moon';
  }

  // ========== Sidebar Management ==========
  function openSidebar() {
    state.sidebarOpen = true;
    elements.sidebar.classList.add('active');
    elements.overlay.classList.add('active');
    document.body.style.overflow = 'hidden';
  }

  function closeSidebar() {
    state.sidebarOpen = false;
    elements.sidebar.classList.remove('active');
    elements.overlay.classList.remove('active');
    document.body.style.overflow = '';
  }

  function toggleSidebar() {
    state.sidebarOpen ? closeSidebar() : openSidebar();
  }

  // ========== Event Listeners ==========
  function setupEventListeners() {
    // Sidebar toggle
    elements.menuToggle?.addEventListener('click', toggleSidebar);
    elements.sidebarClose?.addEventListener('click', closeSidebar);
    elements.overlay?.addEventListener('click', closeSidebar);

    // Theme toggle
    elements.themeToggle?.addEventListener('click', toggleTheme);

    // Navigation links (Explicit Handling)
    // We select fresh elements here to be absolutely sure
    const allNavLinks = document.querySelectorAll('.nav-link');
    
    allNavLinks.forEach(link => {
      link.addEventListener('click', (e) => {
        // Handle external links
        if (link.getAttribute('target') === '_blank') return;
        
        e.preventDefault();
        
        // 1. Close Sidebar FIRST if on mobile
        // function is defined above, but we can also force it here
        if (window.innerWidth <= 992) {
          state.sidebarOpen = false;
          elements.sidebar.classList.remove('active');
          elements.overlay.classList.remove('active');
          document.body.style.overflow = '';
        }

        // 2. Then Navigate
        const page = link.getAttribute('data-page');
        if (page) {
          navigateTo(page);
        }
      });
    });

    // Hash change event for browser back/forward
    window.addEventListener('hashchange', handleRouteChange);

    // Close sidebar on escape key
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape' && state.sidebarOpen) {
        closeSidebar();
      }
    });

    // Handle responsive sidebar on resize
    window.addEventListener('resize', () => {
      if (window.innerWidth > 992 && state.sidebarOpen) {
        closeSidebar();
      }
    });
  }

  // ========== Router ==========
  function handleInitialRoute() {
    const hash = window.location.hash.slice(2); // Remove '#/'
    if (hash && window.documentation && window.documentation[hash]) {
      loadPage(hash);
    } else {
      // Replace state to avoid history pollution on initial load if needed, 
      // but navigateTo is fine for now
      navigateTo('home');
    }
  }

  function handleRouteChange() {
    const hash = window.location.hash.slice(2);
    if (hash && window.documentation && window.documentation[hash]) {
      loadPage(hash);
    } else {
      navigateTo('home');
    }
  }

  function navigateTo(page) {
    window.location.hash = `/${page}`;
    // don't call loadPage here, let hashchange handle it
  }

  function loadPage(page) {
    state.currentPage = page;
    updateActiveNav(page);
    
    // Render content immediately
    renderPage(page);
    
    // Force Scroll to Top "Hammer"
    // Repeatedly force scroll for 300ms to fight browser restoration
    const scrollHammer = setInterval(() => {
      window.scrollTo(0, 0);
      document.documentElement.scrollTop = 0;
      document.body.scrollTop = 0;
    }, 10);

    // Stop hammering after 300ms
    setTimeout(() => {
      clearInterval(scrollHammer);
    }, 300);
  }

  function updateActiveNav(page) {
    elements.navLinks.forEach(link => {
      const linkPage = link.getAttribute('data-page');
      if (linkPage === page) {
        link.classList.add('active');
      } else {
        link.classList.remove('active');
      }
    });
  }

  // ========== Page Rendering ==========
  function renderPage(page) {
    const pageData = window.documentation?.[page];
    
    if (!pageData) {
      renderNotFound();
      return;
    }

    let html = `
      <div class="doc-page">
        <div id="page-top" tabindex="-1" style="position: absolute; top: 0; left: 0; width: 1px; height: 1px; opacity: 0; outline: none;"></div>
    `;

    pageData.sections.forEach(section => {
      html += `
        <div class="section">
          <h2 class="section-title">
            <i class="${section.icon}"></i>
            ${section.title}
          </h2>
          <div class="section-content">
            ${section.content}
          </div>
        </div>
      `;
    });

    html += `</div>`;
    
    elements.pageContent.innerHTML = html;
    
    // Highlight syntax after rendering
    if (window.Prism) {
      setTimeout(() => {
         window.Prism.highlightAll();
      }, 0);
    }
  }

  function renderNotFound() {
    elements.pageContent.innerHTML = `
      <div class="doc-page">
        <div class="page-header">
          <h1 class="page-title">404 - Page Not Found</h1>
          <p class="page-subtitle">The requested documentation page could not be found.</p>
        </div>
        <div class="section">
          <div class="section-content">
            <p>The page you're looking for doesn't exist. Please select a page from the sidebar menu.</p>
            <a href="#/home" class="btn-primary" style="margin-top: 1rem;">
              <i class="fas fa-home"></i> Go to Homepage
            </a>
          </div>
        </div>
      </div>
    `;
  }

  // ========== Search Functionality ==========
  function setupSearch() {
    if (!elements.searchInput) return;

    elements.searchInput.addEventListener('input', (e) => {
      const query = e.target.value.toLowerCase().trim();
      filterNavigation(query);
    });
  }

  function filterNavigation(query) {
    if (!query) {
      // Show all items
      document.querySelectorAll('.nav-section').forEach(section => {
        section.style.display = '';
      });
      elements.navLinks.forEach(link => {
        link.parentElement.style.display = '';
      });
      return;
    }

    // Filter navigation items
    elements.navLinks.forEach(link => {
      const text = link.textContent.toLowerCase();
      const matches = text.includes(query);
      link.parentElement.style.display = matches ? '' : 'none';
    });

    // Hide empty sections
    document.querySelectorAll('.nav-section').forEach(section => {
      const visibleLinks = section.querySelectorAll('.nav-list li:not([style*="display: none"])');
      section.style.display = visibleLinks.length > 0 ? '' : 'none';
    });
  }

  // ========== Utility Functions ==========
  function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
  }

  // ========== Start Application ==========
  // Wait for DOM and documentation to be ready
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

})();

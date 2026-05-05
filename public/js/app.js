/**
 * MedicalOT - Sistema de Notificaciones y Utilidades
 */

// Sistema de Toast Notifications
const Toast = {
  container: null,
  
  init() {
    if (!this.container) {
      this.container = document.createElement('div');
      this.container.className = 'toast-container';
      document.body.appendChild(this.container);
    }
  },
  
  show(message, type = 'info', title = null, duration = 5000) {
    this.init();
    
    const toast = document.createElement('div');
    toast.className = `toast ${type}`;
    
    const icons = {
      success: '<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>',
      error: '<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>',
      warning: '<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>',
      info: '<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>'
    };
    
    const defaultTitles = {
      success: '¡Éxito!',
      error: 'Error',
      warning: 'Advertencia',
      info: 'Información'
    };
    
    toast.innerHTML = `
      <div class="toast-icon">${icons[type]}</div>
      <div class="toast-content">
        <div class="toast-title">${title || defaultTitles[type]}</div>
        <div class="toast-message">${message}</div>
      </div>
      <button class="toast-close" onclick="Toast.hide(this.parentElement)">
        <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
        </svg>
      </button>
    `;
    
    this.container.appendChild(toast);
    
    // Auto-hide
    if (duration > 0) {
      setTimeout(() => this.hide(toast), duration);
    }
    
    return toast;
  },
  
  hide(toast) {
    if (!toast.classList.contains('hiding')) {
      toast.classList.add('hiding');
      setTimeout(() => toast.remove(), 300);
    }
  },
  
  success(message, title = null) {
    return this.show(message, 'success', title);
  },
  
  error(message, title = null) {
    return this.show(message, 'error', title);
  },
  
  warning(message, title = null) {
    return this.show(message, 'warning', title);
  },
  
  info(message, title = null) {
    return this.show(message, 'info', title);
  }
};

// Utilidad: Fecha y Hora
function updateDateTime() {
  const now = new Date();
  const options = { 
    weekday: 'long', 
    year: 'numeric', 
    month: 'long', 
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  };
  return now.toLocaleDateString('es-CL', options);
}

// Utilidad: Inicializar Header
function initHeader(moduleName, userRole, userName) {
  const header = document.querySelector('.main-header');
  if (!header) return;
  
  // Actualizar módulo
  const moduleTitle = header.querySelector('.header-module-title');
  if (moduleTitle) moduleTitle.textContent = moduleName;
  
  // Actualizar rol
  const roleText = header.querySelector('.header-role');
  if (roleText) roleText.textContent = userRole;
  
  // Actualizar fecha/hora
  const dateTime = header.querySelector('.header-datetime');
  if (dateTime) {
    dateTime.innerHTML = `
      <div style="font-weight: 600; color: var(--gray-800);">${updateDateTime()}</div>
      <div style="font-size: 0.75rem;">Sistema MedicalOT</div>
    `;
    setInterval(() => {
      dateTime.querySelector('div').textContent = updateDateTime();
    }, 60000);
  }
  
  // Actualizar usuario
  const userText = header.querySelector('.header-user span');
  if (userText) userText.textContent = userName;
  
  // Avatar con iniciales
  const avatar = header.querySelector('.user-avatar');
  if (avatar) {
    avatar.textContent = userName.split(' ').map(n => n[0]).join('').toUpperCase().slice(0, 2);
  }
  
  // Menú desplegable
  const menuDots = header.querySelector('.menu-dots');
  const dropdown = header.querySelector('.dropdown-menu');
  
  if (menuDots && dropdown) {
    menuDots.addEventListener('click', (e) => {
      e.stopPropagation();
      dropdown.classList.toggle('active');
    });
    
    document.addEventListener('click', () => {
      dropdown.classList.remove('active');
    });
  }
}

// Utilidad: Validar sesión
function checkAuth() {
  const session = sessionStorage.getItem('medicalot_session');
  if (!session && !window.location.pathname.includes('login')) {
    window.location.href = '/login.php';
    return false;
  }
  return session ? JSON.parse(session) : null;
}

// Utilidad: Cerrar sesión
function logout() {
  sessionStorage.removeItem('medicalot_session');
  Toast.success('Sesión cerrada correctamente', 'Hasta pronto');
  setTimeout(() => {
    window.location.href = '/login.php';
  }, 1000);
}

// Inicialización global
document.addEventListener('DOMContentLoaded', () => {
  // Inicializar Toast
  Toast.init();
  
  // Actualizar datetime cada minuto
  setInterval(() => {
    const dt = document.querySelector('.header-datetime div');
    if (dt) dt.textContent = updateDateTime();
  }, 60000);
});

// Exportar para uso global
window.Toast = Toast;
window.updateDateTime = updateDateTime;
window.initHeader = initHeader;
window.checkAuth = checkAuth;
window.logout = logout;
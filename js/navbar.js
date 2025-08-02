// Bulma navbar burger toggle
// This script enables the burger menu for mobile navigation

document.addEventListener('DOMContentLoaded', () => {
  const $navbarBurgers = Array.prototype.slice.call(document.querySelectorAll('.navbar-burger'), 0);
  if ($navbarBurgers.length > 0) {
    $navbarBurgers.forEach( el => {
      el.addEventListener('click', () => {
        const target = el.dataset.target;
        const $target = document.getElementById(target);
        el.classList.toggle('is-active');
        $target.classList.toggle('is-active');
      });
    });
  }

  // Enhanced dropdown functionality
  const dropdowns = document.querySelectorAll('.dropdown');
  dropdowns.forEach(dropdown => {
    const trigger = dropdown.querySelector('.dropdown-trigger button');
    const menu = dropdown.querySelector('.dropdown-menu');
    let closeTimeout;
    
    // Clear any existing timeout
    const clearCloseTimeout = () => {
      if (closeTimeout) {
        clearTimeout(closeTimeout);
        closeTimeout = null;
      }
    };
    
    // Show dropdown immediately on hover
    dropdown.addEventListener('mouseenter', () => {
      clearCloseTimeout();
      dropdown.classList.add('is-active');
    });
    
    // Hide dropdown with delay when mouse leaves
    dropdown.addEventListener('mouseleave', () => {
      closeTimeout = setTimeout(() => {
        dropdown.classList.remove('is-active');
      }, 500); // 500ms delay
    });
    
    // Toggle dropdown on click for mobile/touch devices
    if (trigger) {
      trigger.addEventListener('click', (e) => {
        e.preventDefault();
        clearCloseTimeout();
        dropdown.classList.toggle('is-active');
      });
    }
    
    // Close dropdown when clicking outside
    document.addEventListener('click', (e) => {
      if (!dropdown.contains(e.target)) {
        clearCloseTimeout();
        dropdown.classList.remove('is-active');
      }
    });
  });
});

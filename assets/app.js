document.addEventListener('submit', (event) => {
  const form = event.target;
  const button = form.querySelector('button[type="submit"]');
  if (!button || button.dataset.busy === '1') {
    return;
  }
  button.dataset.busy = '1';
  button.classList.add('is-busy');
});


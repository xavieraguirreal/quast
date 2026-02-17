// Variables globales
let currentSection = 0;
let totalSections = 0;

document.addEventListener('DOMContentLoaded', function() {
    const sections = document.querySelectorAll('.section');
    totalSections = sections.length;

    // Actualizar contador
    document.getElementById('total-sections').textContent = totalSections;

    // Manejar checkboxes con texto adicional
    document.querySelectorAll('.option-card.checkbox input[type="checkbox"]').forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            const textoInput = this.closest('.option-card').querySelector('.texto-adicional');
            if (textoInput) {
                textoInput.disabled = !this.checked;
                if (!this.checked) textoInput.value = '';
            }

            // Si es "ninguna", desmarcar las demás
            if (this.value && this.dataset.ninguna) {
                const group = this.closest('.options-group');
                group.querySelectorAll('input[type="checkbox"]').forEach(cb => {
                    if (cb !== this) cb.checked = false;
                });
            }
        });
    });

    // Form submit
    document.getElementById('survey-form').addEventListener('submit', handleSubmit);

    // Actualizar progress inicial
    updateProgress();
});

function startSurvey() {
    document.getElementById('intro-screen').classList.remove('active');
    document.getElementById('survey-form').classList.add('active');
    window.scrollTo(0, 0);
}

function nextSection() {
    const currentSec = document.querySelector(`.section[data-section="${currentSection}"]`);

    // Validar sección actual
    if (!validateSection(currentSec)) {
        return;
    }

    // Ocultar actual
    currentSec.style.display = 'none';

    // Mostrar siguiente
    currentSection++;
    const nextSec = document.querySelector(`.section[data-section="${currentSection}"]`);
    nextSec.style.display = 'block';

    updateProgress();
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function prevSection() {
    const currentSec = document.querySelector(`.section[data-section="${currentSection}"]`);
    currentSec.style.display = 'none';

    currentSection--;
    const prevSec = document.querySelector(`.section[data-section="${currentSection}"]`);
    prevSec.style.display = 'block';

    updateProgress();
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function updateProgress() {
    const progress = ((currentSection + 1) / totalSections) * 100;
    document.getElementById('progress-fill').style.width = progress + '%';
    document.getElementById('current-section').textContent = currentSection + 1;
}

function validateSection(section) {
    let valid = true;
    const requiredQuestions = section.querySelectorAll('.question');

    requiredQuestions.forEach(q => {
        q.classList.remove('error');
        const existing = q.querySelector('.error-message');
        if (existing) existing.remove();

        const tipo = q.dataset.tipo;
        const preguntaId = q.dataset.preguntaId;

        // Verificar si es requerida
        const isRequired = q.querySelector('[required]') !== null ||
                          q.querySelector('.required') !== null;

        if (!isRequired) return;

        let hasValue = false;

        if (tipo === 'radio') {
            hasValue = q.querySelector(`input[name="pregunta_${preguntaId}"]:checked`) !== null;
        } else if (tipo === 'checkbox') {
            hasValue = q.querySelector(`input[name="pregunta_${preguntaId}[]"]:checked`) !== null;
        } else if (tipo === 'number' || tipo === 'text') {
            const input = q.querySelector(`input[name="pregunta_${preguntaId}"]`);
            hasValue = input && input.value.trim() !== '';
        } else if (tipo === 'textarea') {
            const textarea = q.querySelector(`textarea[name="pregunta_${preguntaId}"]`);
            hasValue = textarea && textarea.value.trim() !== '';
        }

        if (!hasValue) {
            valid = false;
            q.classList.add('error');
            const errorDiv = document.createElement('div');
            errorDiv.className = 'error-message';
            errorDiv.textContent = 'Esta pregunta es obligatoria';
            q.appendChild(errorDiv);
        }
    });

    if (!valid) {
        const firstError = section.querySelector('.question.error');
        if (firstError) {
            firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    }

    return valid;
}

async function handleSubmit(e) {
    e.preventDefault();

    const currentSec = document.querySelector(`.section[data-section="${currentSection}"]`);
    if (!validateSection(currentSec)) {
        return;
    }

    const submitBtn = document.querySelector('.btn-submit');
    submitBtn.classList.add('loading');
    submitBtn.disabled = true;

    const formData = new FormData(e.target);
    formData.append('encuesta_id', e.target.dataset.encuestaId);

    try {
        const response = await fetch(BASE_URL + '/api/submit.php', {
            method: 'POST',
            body: formData
        });

        const result = await response.json();

        if (result.success) {
            document.getElementById('survey-form').classList.remove('active');
            document.getElementById('thank-you-screen').classList.add('active');
        } else {
            alert('Error al enviar: ' + (result.error || 'Intentá de nuevo'));
            submitBtn.classList.remove('loading');
            submitBtn.disabled = false;
        }
    } catch (error) {
        console.error('Error:', error);
        alert('Error de conexión. Verificá tu internet e intentá de nuevo.');
        submitBtn.classList.remove('loading');
        submitBtn.disabled = false;
    }
}

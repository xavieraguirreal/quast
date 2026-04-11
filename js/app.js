// Variables globales
let currentSection = 0;
let totalSections = 0;
let existingResponseId = null; // Para actualizar en vez de crear

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

            // Manejar subpreguntas condicionales (depends_on)
            handleDependsOn(this);
        });
    });

    // Manejar radios con depends_on
    document.querySelectorAll('.option-card input[type="radio"]').forEach(radio => {
        radio.addEventListener('change', function() {
            handleDependsOn(this);
        });
    });

    // Detectar selección de nombre para cargar respuestas previas
    document.querySelectorAll('select').forEach(select => {
        select.addEventListener('change', function() {
            const question = this.closest('.question');
            if (question && question.dataset.codigo === 'nombre_integrante') {
                checkPreviousResponse(this.value);
            }
        });
    });

    // Form submit
    document.getElementById('survey-form').addEventListener('submit', handleSubmit);

    // Prevenir envío con Enter (excepto en última sección)
    document.getElementById('survey-form').addEventListener('keydown', function(e) {
        if (e.key === 'Enter' && e.target.tagName !== 'TEXTAREA') {
            e.preventDefault();
            // Si no es la última sección, avanzar
            if (currentSection < totalSections - 1) {
                nextSection();
            }
        }
    });

    // Actualizar progress inicial
    updateProgress();
});

function startSurvey() {
    document.getElementById('intro-screen').classList.remove('active');
    document.getElementById('survey-form').classList.add('active');
    window.scrollTo(0, 0);
}

async function checkPreviousResponse(opcionId) {
    if (!opcionId) return;

    const encuestaId = document.getElementById('survey-form').dataset.encuestaId;

    try {
        const response = await fetch(
            BASE_URL + '/api/check_response.php?encuesta_id=' + encuestaId + '&opcion_id=' + opcionId
        );
        const result = await response.json();

        // Remover banner previo si existe
        const oldBanner = document.querySelector('.edit-banner');
        if (oldBanner) oldBanner.remove();

        if (result.success && result.exists) {
            existingResponseId = result.respuesta_id;

            // Mostrar banner informativo
            const section = document.querySelector('.section[data-section="0"]');
            const banner = document.createElement('div');
            banner.className = 'edit-banner';
            banner.innerHTML = '<strong>Ya respondiste esta encuesta.</strong> Podes revisar y modificar tus respuestas.';
            section.querySelector('.questions').prepend(banner);

            // Cambiar texto del botón de envío
            const submitBtn = document.querySelector('.btn-submit');
            if (submitBtn) {
                submitBtn.innerHTML = 'Actualizar Respuestas <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 13l4 4L19 7"/></svg>';
            }

            // Poblar formulario con respuestas previas
            populateForm(result.respuestas);
        } else {
            existingResponseId = null;
            // Limpiar formulario si cambia de persona
            clearForm();

            // Restaurar botón
            const submitBtn = document.querySelector('.btn-submit');
            if (submitBtn) {
                submitBtn.innerHTML = 'Enviar Encuesta <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 13l4 4L19 7"/></svg>';
            }
        }
    } catch (error) {
        console.error('Error verificando respuesta previa:', error);
    }
}

function populateForm(respuestas) {
    // Primero limpiar todo (excepto el select de nombre)
    clearForm();

    for (const [preguntaId, data] of Object.entries(respuestas)) {
        const question = document.querySelector(`.question[data-pregunta-id="${preguntaId}"]`);
        if (!question) continue;

        const tipo = question.dataset.tipo;

        if (tipo === 'radio' && data.opciones.length > 0) {
            const radio = question.querySelector(`input[type="radio"][value="${data.opciones[0]}"]`);
            if (radio) {
                radio.checked = true;
                // Disparar depends_on
                handleDependsOn(radio);
            }
        } else if (tipo === 'checkbox' && data.opciones.length > 0) {
            data.opciones.forEach(opId => {
                const cb = question.querySelector(`input[type="checkbox"][value="${opId}"]`);
                if (cb) {
                    cb.checked = true;
                    // Habilitar texto adicional si aplica
                    const textoInput = cb.closest('.option-card').querySelector('.texto-adicional');
                    if (textoInput) {
                        textoInput.disabled = false;
                        if (data.textos_adicionales && data.textos_adicionales[opId]) {
                            textoInput.value = data.textos_adicionales[opId];
                        }
                    }
                }
            });
            // Disparar depends_on para el último checkbox
            const lastCb = question.querySelector('input[type="checkbox"]:checked');
            if (lastCb) handleDependsOn(lastCb);
        } else if (tipo === 'select' && data.opciones.length > 0) {
            const select = question.querySelector('select');
            if (select) select.value = data.opciones[0];
        } else if ((tipo === 'text' || tipo === 'number') && data.valor !== null) {
            const input = question.querySelector(`input[name="pregunta_${preguntaId}"]`);
            if (input) input.value = data.valor;
        } else if (tipo === 'textarea' && data.valor !== null) {
            const textarea = question.querySelector(`textarea[name="pregunta_${preguntaId}"]`);
            if (textarea) textarea.value = data.valor;
        }
    }
}

function clearForm() {
    // Limpiar todo excepto el select de nombre_integrante
    document.querySelectorAll('.question').forEach(q => {
        if (q.dataset.codigo === 'nombre_integrante') return;

        q.querySelectorAll('input[type="radio"], input[type="checkbox"]').forEach(i => i.checked = false);
        q.querySelectorAll('input[type="text"], input[type="number"], textarea').forEach(i => i.value = '');
        q.querySelectorAll('select').forEach(s => s.value = '');
        q.querySelectorAll('.texto-adicional').forEach(t => { t.value = ''; t.disabled = true; });
    });

    // Ocultar preguntas condicionales
    document.querySelectorAll('[data-depends-on]').forEach(dep => {
        dep.style.display = 'none';
    });
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

        // No validar preguntas ocultas (depends_on no activo)
        if (q.style.display === 'none') return;

        // Subpregunta condicional visible: es obligatoria al haberse activado
        const isConditionalVisible = q.dataset.dependsOn !== undefined;

        // Verificar si es requerida
        const isRequired = isConditionalVisible ||
                          q.querySelector('[required]') !== null ||
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
        } else if (tipo === 'select') {
            const select = q.querySelector(`select[name="pregunta_${preguntaId}"]`);
            hasValue = select && select.value !== '';
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

function handleDependsOn(inputEl) {
    const parentQuestion = inputEl.closest('.question');
    if (!parentQuestion) return;
    const parentCodigo = parentQuestion.dataset.codigo;
    if (!parentCodigo) return;

    // Get all checked values in this parent question
    const checkedValores = new Set();
    parentQuestion.querySelectorAll('input:checked').forEach(ci => {
        if (ci.dataset.opcionValor) checkedValores.add(ci.dataset.opcionValor);
    });

    // Show/hide dependent questions
    document.querySelectorAll(`[data-depends-on^="${parentCodigo}:"]`).forEach(dep => {
        const requiredValue = dep.dataset.dependsOn.split(':')[1];
        const show = checkedValores.has(requiredValue);

        dep.style.display = show ? '' : 'none';

        // Mostrar/ocultar asterisco de obligatoria
        const label = dep.querySelector('.question-label');
        if (label) {
            let mark = label.querySelector('.required-dynamic');
            if (show && !mark) {
                mark = document.createElement('span');
                mark.className = 'required required-dynamic';
                mark.textContent = ' *';
                label.appendChild(mark);
            } else if (!show && mark) {
                mark.remove();
            }
        }

        // Clear values and error state when hiding
        if (!show) {
            dep.querySelectorAll('input[type="radio"], input[type="checkbox"]').forEach(i => i.checked = false);
            dep.querySelectorAll('input[type="text"], input[type="number"], textarea, select').forEach(i => i.value = '');
            dep.classList.remove('error');
            const err = dep.querySelector('.error-message');
            if (err) err.remove();
        }
    });
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

    // Si es actualización, enviar el ID existente
    if (existingResponseId) {
        formData.append('respuesta_id', existingResponseId);
    }

    try {
        const response = await fetch(BASE_URL + '/api/submit.php', {
            method: 'POST',
            body: formData
        });

        const result = await response.json();

        if (result.success) {
            document.getElementById('survey-form').classList.remove('active');

            // Cambiar mensaje de agradecimiento si es actualización
            const thankYou = document.getElementById('thank-you-screen');
            if (existingResponseId) {
                thankYou.querySelector('h1').textContent = 'Respuestas actualizadas';
                thankYou.querySelector('p').textContent = 'Tus respuestas fueron modificadas correctamente.';
            }

            thankYou.classList.add('active');
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

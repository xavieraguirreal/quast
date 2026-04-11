-- =============================================
-- UPDATE: Agregar depends_on y limpiar textos de subpreguntas
-- Encuesta SAJuR compromiso-2026
-- Ejecutar en verumax_quast
-- =============================================

USE verumax_quast;

-- Limpiar textos y agregar depends_on a subpreguntas
UPDATE preguntas SET
    texto = 'Cuantas horas semanales aprox?',
    config = '{"depends_on": "tipos_aporte:tiempo"}'
WHERE codigo = 'tiempo_horas';

UPDATE preguntas SET
    texto = 'En que franja horaria?',
    config = '{"depends_on": "tipos_aporte:tiempo"}'
WHERE codigo = 'tiempo_franja';

UPDATE preguntas SET
    texto = 'En que tipo de tareas concretas?',
    config = '{"placeholder": "Ej: redaccion de documentos, capacitaciones, asesoramiento legal...", "rows": 2, "depends_on": "tipos_aporte:trabajo_profesional"}'
WHERE codigo = 'profesional_tareas';

UPDATE preguntas SET
    texto = 'Que areas te interesan?',
    config = '{"placeholder": "Ej: comunicacion, logistica, eventos...", "rows": 2, "depends_on": "tipos_aporte:trabajo_voluntario"}'
WHERE codigo = 'voluntario_areas';

UPDATE preguntas SET
    texto = 'Por que canal?',
    config = '{"depends_on": "tipos_aporte:difusion"}'
WHERE codigo = 'difusion_canales';

UPDATE preguntas SET
    texto = 'Con que sectores?',
    config = '{"depends_on": "tipos_aporte:contactos"}'
WHERE codigo = 'contactos_sectores';

UPDATE preguntas SET
    texto = 'Contanos en una linea',
    config = '{"placeholder": "Tu idea o proyecto...", "depends_on": "tipos_aporte:ideas_proyectos"}'
WHERE codigo = 'ideas_proyectos_desc';

UPDATE preguntas SET
    texto = 'Cuantas, aprox?',
    config = '{"depends_on": "tipos_aporte:traer_personas"}'
WHERE codigo = 'traer_personas_cant';

UPDATE preguntas SET
    texto = 'Especifica cuales',
    config = '{"placeholder": "Ej: sala de reuniones, proyector, vehiculo...", "depends_on": "tipos_aporte:recursos_materiales"}'
WHERE codigo = 'recursos_detalle';

-- Lenguaje inclusivo en instrucciones
UPDATE encuestas SET
    instrucciones = 'Esta encuesta es para los y las integrantes actuales de SAJuR.\n\nTe va a llevar menos de 5 minutos.\n\nTus respuestas nos ayudan a organizar mejor el equipo y aprovechar lo que cada persona puede dar.\n\nNo hay respuestas correctas ni incorrectas: lo importante es que seas sincero/a.'
WHERE codigo = 'compromiso-2026' AND tenant_slug = 'sajur';

SELECT 'depends_on y lenguaje inclusivo actualizados' as resultado;

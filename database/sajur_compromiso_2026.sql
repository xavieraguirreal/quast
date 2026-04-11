-- =============================================
-- QUAST - Encuesta SAJuR: Compromiso 2026
-- URL: quast.verumax.com/sajur/compromiso-2026
-- Fecha: 2026-04-11
-- =============================================

USE verumax_quast;

-- =============================================
-- TENANT: SAJuR
-- =============================================
INSERT INTO tenants (slug, nombre) VALUES ('sajur', 'SAJuR - Sociedad Argentina de Justicia Restaurativa')
ON DUPLICATE KEY UPDATE nombre = VALUES(nombre), activo = 1;

-- =============================================
-- ENCUESTA: Compromiso 2026
-- =============================================
INSERT INTO encuestas (tenant_slug, codigo, titulo, descripcion, instrucciones, activa)
VALUES (
    'sajur',
    'compromiso-2026',
    'SAJuR - Aportes, Expectativas y Compromiso',
    'Encuesta interna para conocer qué podés aportar, qué esperás y cómo te gustaría participar en SAJuR durante los próximos meses.',
    'Esta encuesta es para los y las integrantes actuales de SAJuR.\n\nTe va a llevar menos de 5 minutos.\n\nTus respuestas nos ayudan a organizar mejor el equipo y aprovechar lo que cada persona puede dar.\n\nNo hay respuestas correctas ni incorrectas: lo importante es que seas sincero/a.',
    1
);

SET @encuesta_id = LAST_INSERT_ID();

-- =============================================
-- SECCION 0: IDENTIFICACION
-- =============================================
INSERT INTO secciones (encuesta_id, numero, titulo, descripcion, orden)
VALUES (@encuesta_id, 0, 'Identificacion', 'Selecciona tu nombre para comenzar.', 0);

SET @seccion_id = LAST_INSERT_ID();

-- Pregunta: Nombre (select con los 9 integrantes)
INSERT INTO preguntas (seccion_id, codigo, texto, tipo, requerida, orden)
VALUES (@seccion_id, 'nombre_integrante', 'Selecciona tu nombre:', 'select', 1, 1);

SET @pregunta_id = LAST_INSERT_ID();

INSERT INTO opciones (pregunta_id, valor, texto, orden) VALUES
(@pregunta_id, 'juanc', 'JuanC', 1),
(@pregunta_id, 'alez', 'AleZ', 2),
(@pregunta_id, 'alea', 'AleA', 3),
(@pregunta_id, 'migue', 'Migue', 4),
(@pregunta_id, 'luz', 'Luz', 5),
(@pregunta_id, 'pampa', 'Pampa', 6),
(@pregunta_id, 'eva', 'Eva', 7),
(@pregunta_id, 'gise', 'Gise', 8),
(@pregunta_id, 'coty', 'Coty', 9);

-- =============================================
-- SECCION 1: QUE PODES APORTAR HOY A SAJUR
-- =============================================
INSERT INTO secciones (encuesta_id, numero, titulo, descripcion, orden)
VALUES (@encuesta_id, 1, 'Que podes aportar hoy a SAJuR', 'Marca todo lo que aplique. Donde aparezcan subpreguntas, completalas para que podamos entender mejor tu aporte.', 1);

SET @seccion_id = LAST_INSERT_ID();

-- Pregunta 1a: Tipos de aporte (multi-select)
INSERT INTO preguntas (seccion_id, codigo, texto, tipo, requerida, orden)
VALUES (@seccion_id, 'tipos_aporte', 'Que tipos de aporte podes hacer hoy?', 'checkbox', 1, 1);

SET @pregunta_id = LAST_INSERT_ID();

INSERT INTO opciones (pregunta_id, valor, texto, orden) VALUES
(@pregunta_id, 'tiempo', 'Tiempo', 1),
(@pregunta_id, 'trabajo_profesional', 'Trabajo profesional en mi area', 2),
(@pregunta_id, 'trabajo_voluntario', 'Trabajo voluntario en otras areas', 3),
(@pregunta_id, 'difusion', 'Difusion', 4),
(@pregunta_id, 'contactos', 'Contactos / vinculos', 5),
(@pregunta_id, 'ideas_proyectos', 'Ideas o proyectos propios para SAJuR', 6),
(@pregunta_id, 'traer_personas', 'Traer nuevas personas', 7),
(@pregunta_id, 'recursos_materiales', 'Recursos materiales (espacio, equipamiento, movilidad)', 8);

-- Pregunta 1b: Tiempo - horas semanales
INSERT INTO preguntas (seccion_id, codigo, texto, tipo, requerida, orden, config)
VALUES (@seccion_id, 'tiempo_horas', 'Cuantas horas semanales aprox?', 'radio', 0, 2, '{"depends_on": "tipos_aporte:tiempo"}');

SET @pregunta_id = LAST_INSERT_ID();

INSERT INTO opciones (pregunta_id, valor, texto, orden) VALUES
(@pregunta_id, 'menos_2', 'Menos de 2 horas', 1),
(@pregunta_id, '2_5', 'De 2 a 5 horas', 2),
(@pregunta_id, '5_10', 'De 5 a 10 horas', 3),
(@pregunta_id, 'mas_10', 'Mas de 10 horas', 4);

-- Pregunta 1c: Tiempo - franja horaria
INSERT INTO preguntas (seccion_id, codigo, texto, tipo, requerida, orden, config)
VALUES (@seccion_id, 'tiempo_franja', 'En que franja horaria?', 'checkbox', 0, 3, '{"depends_on": "tipos_aporte:tiempo"}');

SET @pregunta_id = LAST_INSERT_ID();

INSERT INTO opciones (pregunta_id, valor, texto, orden) VALUES
(@pregunta_id, 'manana', 'Manana', 1),
(@pregunta_id, 'tarde', 'Tarde', 2),
(@pregunta_id, 'noche', 'Noche', 3),
(@pregunta_id, 'finde', 'Fin de semana', 4);

-- Pregunta 1d: Trabajo profesional - tareas concretas
INSERT INTO preguntas (seccion_id, codigo, texto, tipo, requerida, orden, config)
VALUES (@seccion_id, 'profesional_tareas', 'En que tipo de tareas concretas?', 'textarea', 0, 4, '{"placeholder": "Ej: redaccion de documentos, capacitaciones, asesoramiento legal...", "rows": 2, "depends_on": "tipos_aporte:trabajo_profesional"}');

-- Pregunta 1e: Trabajo voluntario - areas de interes
INSERT INTO preguntas (seccion_id, codigo, texto, tipo, requerida, orden, config)
VALUES (@seccion_id, 'voluntario_areas', 'Que areas te interesan?', 'textarea', 0, 5, '{"placeholder": "Ej: comunicacion, logistica, eventos...", "rows": 2, "depends_on": "tipos_aporte:trabajo_voluntario"}');

-- Pregunta 1f: Difusion - canales
INSERT INTO preguntas (seccion_id, codigo, texto, tipo, requerida, orden, config)
VALUES (@seccion_id, 'difusion_canales', 'Por que canal?', 'checkbox', 0, 6, '{"depends_on": "tipos_aporte:difusion"}');

SET @pregunta_id = LAST_INSERT_ID();

INSERT INTO opciones (pregunta_id, valor, texto, orden) VALUES
(@pregunta_id, 'redes_propias', 'Redes propias', 1),
(@pregunta_id, 'medios', 'Medios de comunicacion', 2),
(@pregunta_id, 'institucional', 'Canal institucional', 3);

-- Pregunta 1g: Contactos - sectores
INSERT INTO preguntas (seccion_id, codigo, texto, tipo, requerida, orden, config)
VALUES (@seccion_id, 'contactos_sectores', 'Con que sectores?', 'checkbox', 0, 7, '{"depends_on": "tipos_aporte:contactos"}');

SET @pregunta_id = LAST_INSERT_ID();

INSERT INTO opciones (pregunta_id, valor, texto, orden) VALUES
(@pregunta_id, 'universidad', 'Universidad', 1),
(@pregunta_id, 'estado', 'Estado', 2),
(@pregunta_id, 'ongs', 'ONGs', 3),
(@pregunta_id, 'medios', 'Medios', 4),
(@pregunta_id, 'privados', 'Sector privado', 5);

-- Pregunta 1h: Ideas o proyectos
INSERT INTO preguntas (seccion_id, codigo, texto, tipo, requerida, orden, config)
VALUES (@seccion_id, 'ideas_proyectos_desc', 'Contanos en una linea', 'text', 0, 8, '{"placeholder": "Tu idea o proyecto...", "depends_on": "tipos_aporte:ideas_proyectos"}');

-- Pregunta 1i: Traer personas
INSERT INTO preguntas (seccion_id, codigo, texto, tipo, requerida, orden, config)
VALUES (@seccion_id, 'traer_personas_cant', 'Cuantas, aprox?', 'radio', 0, 9, '{"depends_on": "tipos_aporte:traer_personas"}');

SET @pregunta_id = LAST_INSERT_ID();

INSERT INTO opciones (pregunta_id, valor, texto, orden) VALUES
(@pregunta_id, '1_2', '1 a 2 personas', 1),
(@pregunta_id, '3_5', '3 a 5 personas', 2),
(@pregunta_id, 'mas_5', 'Mas de 5 personas', 3);

-- Pregunta 1j: Recursos materiales
INSERT INTO preguntas (seccion_id, codigo, texto, tipo, requerida, orden, config)
VALUES (@seccion_id, 'recursos_detalle', 'Especifica cuales', 'text', 0, 10, '{"placeholder": "Ej: sala de reuniones, proyector, vehiculo...", "depends_on": "tipos_aporte:recursos_materiales"}');

-- =============================================
-- SECCION 2: QUE ESPERAS DE SAJUR
-- =============================================
INSERT INTO secciones (encuesta_id, numero, titulo, descripcion, orden)
VALUES (@encuesta_id, 2, 'Que esperas de SAJuR', 'Marca todo lo que aplique.', 2);

SET @seccion_id = LAST_INSERT_ID();

-- Pregunta 2a: Expectativas (multi-select)
INSERT INTO preguntas (seccion_id, codigo, texto, tipo, requerida, orden)
VALUES (@seccion_id, 'expectativas', 'Que esperas obtener de tu participacion en SAJuR?', 'checkbox', 1, 1);

SET @pregunta_id = LAST_INSERT_ID();

INSERT INTO opciones (pregunta_id, valor, texto, orden, permite_texto_adicional) VALUES
(@pregunta_id, 'aprendizaje', 'Aprendizaje / formacion', 1, 0),
(@pregunta_id, 'acompanamiento', 'Acompanamiento profesional', 2, 0),
(@pregunta_id, 'visibilidad', 'Visibilidad / prestigio profesional', 3, 0),
(@pregunta_id, 'red_contactos', 'Red de contactos', 4, 0),
(@pregunta_id, 'ingresos', 'Ingresos economicos', 5, 0),
(@pregunta_id, 'pertenencia', 'Sentido de pertenencia / causa', 6, 0),
(@pregunta_id, 'experiencia', 'Experiencia practica (casos, intervenciones)', 7, 0),
(@pregunta_id, 'otro', 'Otro', 8, 1);

-- =============================================
-- SECCION 3: COMPROMISO REAL EN LOS PROXIMOS 3 MESES
-- =============================================
INSERT INTO secciones (encuesta_id, numero, titulo, descripcion, orden)
VALUES (@encuesta_id, 3, 'Compromiso real en los proximos 3 meses', 'Esta seccion nos ayuda a entender como organizar mejor el equipo.', 3);

SET @seccion_id = LAST_INSERT_ID();

-- Pregunta 3a: Disponibilidad escala 1-5
INSERT INTO preguntas (seccion_id, codigo, texto, tipo, requerida, orden)
VALUES (@seccion_id, 'disponibilidad_escala', 'En escala 1 a 5, que tan disponible te sentis para aportar en los proximos 3 meses?', 'radio', 1, 1);

SET @pregunta_id = LAST_INSERT_ID();

INSERT INTO opciones (pregunta_id, valor, texto, orden) VALUES
(@pregunta_id, '1', '1 - Muy poca disponibilidad', 1),
(@pregunta_id, '2', '2 - Poca disponibilidad', 2),
(@pregunta_id, '3', '3 - Disponibilidad moderada', 3),
(@pregunta_id, '4', '4 - Buena disponibilidad', 4),
(@pregunta_id, '5', '5 - Totalmente disponible', 5);

-- Pregunta 3b: Tipo de tareas
INSERT INTO preguntas (seccion_id, codigo, texto, tipo, requerida, orden)
VALUES (@seccion_id, 'tipo_tareas', 'Preferis tareas puntuales o sostenidas?', 'radio', 1, 2);

SET @pregunta_id = LAST_INSERT_ID();

INSERT INTO opciones (pregunta_id, valor, texto, orden) VALUES
(@pregunta_id, 'puntuales', 'Puntuales (cosas concretas, de una vez)', 1),
(@pregunta_id, 'sostenidas', 'Sostenidas (compromiso regular)', 2),
(@pregunta_id, 'ambas', 'Ambas, segun la necesidad', 3);

-- Pregunta 3c: Que te frena
INSERT INTO preguntas (seccion_id, codigo, texto, tipo, requerida, orden, config)
VALUES (@seccion_id, 'que_te_frena', 'Hay algo que hoy te frena para involucrarte mas?', 'textarea', 0, 3, '{"placeholder": "Si hay algo, contanos...", "rows": 3}');

-- Pregunta 3d: En que te gustaria que SAJuR te pida ayuda (LA CLAVE)
INSERT INTO preguntas (seccion_id, codigo, texto, tipo, requerida, orden, config)
VALUES (@seccion_id, 'pedir_ayuda', 'En que te gustaria que SAJuR te pida ayuda concretamente?', 'textarea', 1, 4, '{"placeholder": "Defini tu rol ideal: en que te gustaria que te convoquemos...", "rows": 3}');

-- =============================================
-- FIN
-- =============================================
SELECT 'Encuesta SAJuR compromiso-2026 creada exitosamente' as resultado;
SELECT CONCAT('URL: quast.verumax.com/sajur/compromiso-2026') as url;

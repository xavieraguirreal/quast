-- =============================================
-- QUAST - Encuesta Semillero UdeA: El Espejo Invertido
-- URL: quast.verumax.com/semillero-udea/el-espejo-invertido
-- Fecha: 2026-04-11
-- =============================================

USE verumax_quast;

-- =============================================
-- TENANT: Semillero UdeA
-- =============================================
INSERT INTO tenants (slug, nombre) VALUES ('semillero-udea', 'Semillero de Penitenciario y Derechos Humanos - UdeA')
ON DUPLICATE KEY UPDATE nombre = VALUES(nombre), activo = 1;

-- =============================================
-- ENCUESTA: El Espejo Invertido
-- =============================================
INSERT INTO encuestas (tenant_slug, codigo, titulo, descripcion, instrucciones, activa)
VALUES (
    'semillero-udea',
    'el-espejo-invertido',
    'El Espejo Invertido',
    'Investigacion sobre dinamicas de violencia horizontal en centros de reclusion femeninos en Colombia. Hary Sacro y Luis Triana - Semillero de Penitenciario y Derechos Humanos, Universidad de Antioquia.',
    'La informacion sera tratada con confidencialidad y anonimato.\n\nLos resultados se presentaran de forma agregada, priorizando el rigor academico.\n\nEsta investigacion busca comprender las dinamicas de violencia horizontal en centros de reclusion femeninos.\n\nTu participacion es fundamental: no hay respuestas correctas ni incorrectas.',
    1
);

SET @encuesta_id = LAST_INSERT_ID();

-- =============================================
-- SECCION 0: DATOS DEMOGRAFICOS
-- =============================================
INSERT INTO secciones (encuesta_id, numero, titulo, descripcion, orden)
VALUES (@encuesta_id, 0, 'Datos Demograficos', 'Informacion basica para contextualizar las respuestas. Es anonima.', 0);

SET @seccion_id = LAST_INSERT_ID();

-- Pregunta: Pais
INSERT INTO preguntas (seccion_id, codigo, texto, tipo, requerida, orden)
VALUES (@seccion_id, 'pais', 'Pais en el que te encontras:', 'select', 1, 1);

SET @pregunta_id = LAST_INSERT_ID();

INSERT INTO opciones (pregunta_id, valor, texto, orden) VALUES
(@pregunta_id, 'colombia', 'Colombia', 1),
(@pregunta_id, 'argentina', 'Argentina', 2),
(@pregunta_id, 'bolivia', 'Bolivia', 3),
(@pregunta_id, 'brasil', 'Brasil', 4),
(@pregunta_id, 'chile', 'Chile', 5),
(@pregunta_id, 'costa-rica', 'Costa Rica', 6),
(@pregunta_id, 'cuba', 'Cuba', 7),
(@pregunta_id, 'ecuador', 'Ecuador', 8),
(@pregunta_id, 'el-salvador', 'El Salvador', 9),
(@pregunta_id, 'espana', 'Espana', 10),
(@pregunta_id, 'guatemala', 'Guatemala', 11),
(@pregunta_id, 'honduras', 'Honduras', 12),
(@pregunta_id, 'mexico', 'Mexico', 13),
(@pregunta_id, 'nicaragua', 'Nicaragua', 14),
(@pregunta_id, 'panama', 'Panama', 15),
(@pregunta_id, 'paraguay', 'Paraguay', 16),
(@pregunta_id, 'peru', 'Peru', 17),
(@pregunta_id, 'puerto-rico', 'Puerto Rico', 18),
(@pregunta_id, 'republica-dominicana', 'Republica Dominicana', 19),
(@pregunta_id, 'uruguay', 'Uruguay', 20),
(@pregunta_id, 'venezuela', 'Venezuela', 21),
(@pregunta_id, 'otro', 'Otro pais', 22);

-- Pregunta: Edad
INSERT INTO preguntas (seccion_id, codigo, texto, tipo, requerida, orden, config)
VALUES (@seccion_id, 'edad', 'Tu edad (opcional):', 'number', 0, 2, '{"min": 18, "max": 99, "placeholder": "Ej: 35"}');

-- Pregunta: Tiempo en reclusion
INSERT INTO preguntas (seccion_id, codigo, texto, tipo, requerida, orden)
VALUES (@seccion_id, 'tiempo_reclusion', 'Tiempo que llevas en reclusion (opcional):', 'radio', 0, 3);

SET @pregunta_id = LAST_INSERT_ID();

INSERT INTO opciones (pregunta_id, valor, texto, orden) VALUES
(@pregunta_id, 'menos-1', 'Menos de 1 ano', 1),
(@pregunta_id, '1-3', 'Entre 1 y 3 anos', 2),
(@pregunta_id, '4-7', 'Entre 4 y 7 anos', 3),
(@pregunta_id, '8-mas', 'Mas de 8 anos', 4),
(@pregunta_id, 'ns-nc', 'Prefiero no responder', 5);

-- =============================================
-- SECCION 1: ADAPTACION AL ENTORNO
-- =============================================
INSERT INTO secciones (encuesta_id, numero, titulo, descripcion, orden)
VALUES (@encuesta_id, 1, 'Adaptacion al Entorno', 'Conocer como ha sido tu proceso de adaptacion a este entorno.', 1);

SET @seccion_id = LAST_INSERT_ID();

INSERT INTO preguntas (seccion_id, codigo, texto, tipo, requerida, orden, config)
VALUES (@seccion_id, 'adaptacion_premios', 'Que comportamientos o actitudes sientes que el sistema o entorno premian o protegen?', 'textarea', 1, 1, '{"rows": 5, "placeholder": "Escribi tu respuesta..."}');

INSERT INTO preguntas (seccion_id, codigo, texto, tipo, requerida, orden, config)
VALUES (@seccion_id, 'adaptacion_esfuerzo', 'Cual entorno (sumision en libertad vs. jerarquia del patio) demando mayor esfuerzo?', 'textarea', 1, 2, '{"rows": 5, "placeholder": "Escribi tu respuesta..."}');

INSERT INTO preguntas (seccion_id, codigo, texto, tipo, requerida, orden, config)
VALUES (@seccion_id, 'adaptacion_identidad', 'Cuando la reclusion incide en tu identidad como mujer, modificando rasgos?', 'textarea', 1, 3, '{"rows": 5, "placeholder": "Escribi tu respuesta..."}');

-- =============================================
-- SECCION 2: VINCULOS Y RELACIONES
-- =============================================
INSERT INTO secciones (encuesta_id, numero, titulo, descripcion, orden)
VALUES (@encuesta_id, 2, 'Vinculos y Relaciones', 'Explorar como nos relacionamos dentro de estos espacios.', 2);

SET @seccion_id = LAST_INSERT_ID();

INSERT INTO preguntas (seccion_id, codigo, texto, tipo, requerida, orden, config)
VALUES (@seccion_id, 'vinculos_solidaridad', 'Como influye el entorno en la solidaridad o los conflictos entre companeras?', 'textarea', 1, 1, '{"rows": 5, "placeholder": "Escribi tu respuesta..."}');

INSERT INTO preguntas (seccion_id, codigo, texto, tipo, requerida, orden, config)
VALUES (@seccion_id, 'vinculos_control', 'Has percibido roles de control o vigilancia? Como las vulneradas asumen autoridad?', 'textarea', 1, 2, '{"rows": 5, "placeholder": "Escribi tu respuesta..."}');

INSERT INTO preguntas (seccion_id, codigo, texto, tipo, requerida, orden, config)
VALUES (@seccion_id, 'vinculos_violencias_previas', 'Como influyen las violencias de genero previas en las relaciones actuales?', 'textarea', 1, 3, '{"rows": 5, "placeholder": "Escribi tu respuesta..."}');

-- =============================================
-- SECCION 3: VIOLENCIAS EXPERIMENTADAS
-- =============================================
INSERT INTO secciones (encuesta_id, numero, titulo, descripcion, orden)
VALUES (@encuesta_id, 3, 'Violencias Experimentadas', 'Marca todas las opciones que apliquen.', 3);

SET @seccion_id = LAST_INSERT_ID();

INSERT INTO preguntas (seccion_id, codigo, texto, tipo, requerida, orden)
VALUES (@seccion_id, 'tipos_violencia', 'Que tipos de violencia has experimentado por no ajustarte a roles?', 'checkbox', 1, 1);

SET @pregunta_id = LAST_INSERT_ID();

INSERT INTO opciones (pregunta_id, valor, texto, orden) VALUES
(@pregunta_id, 'verbal_estereotipos', 'Violencia verbal basada en estereotipos', 1),
(@pregunta_id, 'verbal_rol_social', 'Violencia verbal sobre rol social', 2),
(@pregunta_id, 'agresiones_fisicas', 'Agresiones fisicas', 3),
(@pregunta_id, 'psicologicas', 'Violencias psicologicas', 4),
(@pregunta_id, 'homofobia', 'Homofobia', 5),
(@pregunta_id, 'transfobia', 'Transfobia', 6),
(@pregunta_id, 'dominacion_emocional', 'Dominacion emocional', 7),
(@pregunta_id, 'rechazo_delitos', 'Rechazo por delitos especificos', 8),
(@pregunta_id, 'dominacion_economica', 'Dominacion economica', 9),
(@pregunta_id, 'aislamiento_custodia', 'Aislamiento por custodia', 10),
(@pregunta_id, 'aislamiento_social', 'Aislamiento social', 11),
(@pregunta_id, 'discursos_machistas', 'Discursos machistas entre pares', 12),
(@pregunta_id, 'normalizacion_insultos', 'Normalizacion de insultos de genero', 13),
(@pregunta_id, 'roles_desiguales', 'Roles desiguales en actividades', 14),
(@pregunta_id, 'dependencia_forzada', 'Dependencia forzada', 15);

-- =============================================
-- SECCION 4: RESISTENCIA Y SOLIDARIDAD
-- =============================================
INSERT INTO secciones (encuesta_id, numero, titulo, descripcion, orden)
VALUES (@encuesta_id, 4, 'Resistencia y Solidaridad', 'Conversar sobre la voluntad y la fuerza para no dejarse anular.', 4);

SET @seccion_id = LAST_INSERT_ID();

INSERT INTO preguntas (seccion_id, codigo, texto, tipo, requerida, orden, config)
VALUES (@seccion_id, 'resistencia_vulnerabilidad', 'Mostrar vulnerabilidad es un riesgo? Las actitudes defensivas son individuales o mecanicas?', 'textarea', 1, 1, '{"rows": 5, "placeholder": "Escribi tu respuesta..."}');

INSERT INTO preguntas (seccion_id, codigo, texto, tipo, requerida, orden, config)
VALUES (@seccion_id, 'resistencia_voluntad', 'Cuanto espacio hay para la voluntad propia? Se castiga rechazar roles de fuerza?', 'textarea', 1, 2, '{"rows": 5, "placeholder": "Escribi tu respuesta..."}');

INSERT INTO preguntas (seccion_id, codigo, texto, tipo, requerida, orden, config)
VALUES (@seccion_id, 'resistencia_cuidado', 'Que actos de cuidado desafian la logica de violencia?', 'textarea', 1, 3, '{"rows": 5, "placeholder": "Escribi tu respuesta..."}');

INSERT INTO preguntas (seccion_id, codigo, texto, tipo, requerida, orden, config)
VALUES (@seccion_id, 'comentarios_finales', 'Si deseas agregar algo mas, podes escribirlo a continuacion (opcional):', 'textarea', 0, 4, '{"rows": 6, "placeholder": "Espacio libre para lo que quieras agregar..."}');

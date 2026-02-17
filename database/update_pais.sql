-- Agregar pregunta de país en la sección de Datos Básicos
-- Primero obtener el ID de la sección 0 (Datos Básicos) de la encuesta

-- Insertar la pregunta de país (orden 0 para que aparezca primero, antes de sexo)
INSERT INTO preguntas (seccion_id, codigo, texto, tipo, requerida, orden)
SELECT s.id, 'pais', '¿En qué país te encontrás?', 'select', 1, 0
FROM secciones s
JOIN encuestas e ON s.encuesta_id = e.id
WHERE e.codigo = 'condiciones-detencion-2026' AND s.numero = 0;

-- Obtener el ID de la pregunta recién insertada y agregar las opciones
SET @pregunta_pais_id = LAST_INSERT_ID();

INSERT INTO opciones (pregunta_id, valor, texto, orden) VALUES
(@pregunta_pais_id, 'AR', 'Argentina', 1),
(@pregunta_pais_id, 'BO', 'Bolivia', 2),
(@pregunta_pais_id, 'BR', 'Brasil', 3),
(@pregunta_pais_id, 'CA', 'Canadá', 4),
(@pregunta_pais_id, 'CL', 'Chile', 5),
(@pregunta_pais_id, 'CO', 'Colombia', 6),
(@pregunta_pais_id, 'CR', 'Costa Rica', 7),
(@pregunta_pais_id, 'CU', 'Cuba', 8),
(@pregunta_pais_id, 'EC', 'Ecuador', 9),
(@pregunta_pais_id, 'SV', 'El Salvador', 10),
(@pregunta_pais_id, 'US', 'Estados Unidos', 11),
(@pregunta_pais_id, 'GT', 'Guatemala', 12),
(@pregunta_pais_id, 'HT', 'Haití', 13),
(@pregunta_pais_id, 'HN', 'Honduras', 14),
(@pregunta_pais_id, 'MX', 'México', 15),
(@pregunta_pais_id, 'NI', 'Nicaragua', 16),
(@pregunta_pais_id, 'PA', 'Panamá', 17),
(@pregunta_pais_id, 'PY', 'Paraguay', 18),
(@pregunta_pais_id, 'PE', 'Perú', 19),
(@pregunta_pais_id, 'PR', 'Puerto Rico', 20),
(@pregunta_pais_id, 'DO', 'República Dominicana', 21),
(@pregunta_pais_id, 'UY', 'Uruguay', 22),
(@pregunta_pais_id, 'VE', 'Venezuela', 23),
(@pregunta_pais_id, 'ES', 'España', 24),
(@pregunta_pais_id, 'otro', 'Otro', 25);

-- Actualizar el orden de las otras preguntas para que país sea primero
UPDATE preguntas SET orden = 2 WHERE codigo = 'sexo';
UPDATE preguntas SET orden = 3 WHERE codigo = 'edad';

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
-- Norteamérica
(@pregunta_pais_id, 'CA', 'Canadá', 1),
(@pregunta_pais_id, 'US', 'Estados Unidos', 2),
(@pregunta_pais_id, 'MX', 'México', 3),
-- Centroamérica
(@pregunta_pais_id, 'BZ', 'Belice', 4),
(@pregunta_pais_id, 'CR', 'Costa Rica', 5),
(@pregunta_pais_id, 'SV', 'El Salvador', 6),
(@pregunta_pais_id, 'GT', 'Guatemala', 7),
(@pregunta_pais_id, 'HN', 'Honduras', 8),
(@pregunta_pais_id, 'NI', 'Nicaragua', 9),
(@pregunta_pais_id, 'PA', 'Panamá', 10),
-- Caribe
(@pregunta_pais_id, 'CU', 'Cuba', 11),
(@pregunta_pais_id, 'HT', 'Haití', 12),
(@pregunta_pais_id, 'JM', 'Jamaica', 13),
(@pregunta_pais_id, 'PR', 'Puerto Rico', 14),
(@pregunta_pais_id, 'DO', 'República Dominicana', 15),
(@pregunta_pais_id, 'TT', 'Trinidad y Tobago', 16),
-- Sudamérica
(@pregunta_pais_id, 'AR', 'Argentina', 17),
(@pregunta_pais_id, 'BO', 'Bolivia', 18),
(@pregunta_pais_id, 'BR', 'Brasil', 19),
(@pregunta_pais_id, 'CL', 'Chile', 20),
(@pregunta_pais_id, 'CO', 'Colombia', 21),
(@pregunta_pais_id, 'EC', 'Ecuador', 22),
(@pregunta_pais_id, 'GY', 'Guyana', 23),
(@pregunta_pais_id, 'PY', 'Paraguay', 24),
(@pregunta_pais_id, 'PE', 'Perú', 25),
(@pregunta_pais_id, 'SR', 'Surinam', 26),
(@pregunta_pais_id, 'UY', 'Uruguay', 27),
(@pregunta_pais_id, 'VE', 'Venezuela', 28),
-- Europa
(@pregunta_pais_id, 'ES', 'España', 29),
-- Otro
(@pregunta_pais_id, 'otro', 'Otro', 30);

-- Actualizar el orden de las otras preguntas para que país sea primero
UPDATE preguntas SET orden = 2 WHERE codigo = 'sexo';
UPDATE preguntas SET orden = 3 WHERE codigo = 'edad';

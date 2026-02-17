-- =============================================
-- ESQUEMA DE BASE DE DATOS PARA SISTEMA DE ENCUESTAS
-- Multitenant y Multi-encuesta
-- =============================================

-- Tabla de clientes/tenants
CREATE TABLE IF NOT EXISTS tenants (
    id INT AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(50) NOT NULL UNIQUE,
    nombre VARCHAR(255) NOT NULL,
    activo TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Tabla de encuestas
CREATE TABLE IF NOT EXISTS encuestas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_slug VARCHAR(50) NOT NULL,
    codigo VARCHAR(100) NOT NULL UNIQUE,
    titulo VARCHAR(255) NOT NULL,
    descripcion TEXT,
    instrucciones TEXT,
    activa TINYINT(1) DEFAULT 1,
    fecha_inicio DATE,
    fecha_fin DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_slug) REFERENCES tenants(slug) ON DELETE CASCADE,
    INDEX idx_tenant_codigo (tenant_slug, codigo)
);

-- Tabla de secciones de encuesta
CREATE TABLE IF NOT EXISTS secciones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    encuesta_id INT NOT NULL,
    numero INT NOT NULL,
    titulo VARCHAR(255) NOT NULL,
    descripcion TEXT,
    orden INT DEFAULT 0,
    FOREIGN KEY (encuesta_id) REFERENCES encuestas(id) ON DELETE CASCADE,
    INDEX idx_encuesta_orden (encuesta_id, orden)
);

-- Tabla de preguntas
CREATE TABLE IF NOT EXISTS preguntas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    seccion_id INT NOT NULL,
    codigo VARCHAR(50) NOT NULL,
    texto TEXT NOT NULL,
    tipo ENUM('radio', 'checkbox', 'text', 'textarea', 'number', 'select') NOT NULL,
    requerida TINYINT(1) DEFAULT 0,
    orden INT DEFAULT 0,
    config JSON, -- Para opciones adicionales como min/max, placeholder, etc.
    FOREIGN KEY (seccion_id) REFERENCES secciones(id) ON DELETE CASCADE,
    INDEX idx_seccion_orden (seccion_id, orden)
);

-- Tabla de opciones de respuesta (para radio, checkbox, select)
CREATE TABLE IF NOT EXISTS opciones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pregunta_id INT NOT NULL,
    valor VARCHAR(100) NOT NULL,
    texto TEXT NOT NULL,
    orden INT DEFAULT 0,
    permite_texto_adicional TINYINT(1) DEFAULT 0, -- Para opciones tipo "Otro: ___"
    FOREIGN KEY (pregunta_id) REFERENCES preguntas(id) ON DELETE CASCADE,
    INDEX idx_pregunta_orden (pregunta_id, orden)
);

-- Tabla de respuestas (una fila por encuesta completada)
CREATE TABLE IF NOT EXISTS respuestas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    encuesta_id INT NOT NULL,
    uuid VARCHAR(36) NOT NULL UNIQUE, -- Identificador anónimo único
    ip_hash VARCHAR(64), -- Hash del IP para detectar duplicados sin identificar
    user_agent TEXT,
    completada TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL,
    FOREIGN KEY (encuesta_id) REFERENCES encuestas(id) ON DELETE CASCADE,
    INDEX idx_encuesta_fecha (encuesta_id, created_at)
);

-- Tabla de respuestas individuales (una fila por cada respuesta a pregunta)
CREATE TABLE IF NOT EXISTS respuestas_detalle (
    id INT AUTO_INCREMENT PRIMARY KEY,
    respuesta_id INT NOT NULL,
    pregunta_id INT NOT NULL,
    opcion_id INT NULL, -- Para respuestas tipo radio/select
    valor TEXT, -- Para respuestas de texto o valor de checkbox
    texto_adicional TEXT, -- Para opciones con "Otro: ___"
    FOREIGN KEY (respuesta_id) REFERENCES respuestas(id) ON DELETE CASCADE,
    FOREIGN KEY (pregunta_id) REFERENCES preguntas(id) ON DELETE CASCADE,
    FOREIGN KEY (opcion_id) REFERENCES opciones(id) ON DELETE SET NULL,
    INDEX idx_respuesta_pregunta (respuesta_id, pregunta_id)
);

-- =============================================
-- DATOS INICIALES
-- =============================================

-- Insertar tenant ALDP
INSERT INTO tenants (slug, nombre) VALUES ('aldp', 'ALDP');

-- Insertar encuesta de condiciones de detención
INSERT INTO encuestas (tenant_slug, codigo, titulo, descripcion, instrucciones, activa)
VALUES (
    'aldp',
    'condiciones-detencion-2026',
    'Encuesta Condiciones de Detención y Derechos',
    'Encuesta anónima sobre condiciones de detención y derechos para mujeres privadas de libertad.',
    'La encuesta es anónima y confidencial: no se pedirá tu nombre ni datos que te identifiquen.\n\nPodés responder solo las preguntas que quieras; no estás obligada a contestar nada que te haga sentir mal.\n\nLas respuestas se usarán para mejorar las condiciones en los centros penitenciarios y defender tus derechos.\n\nHay un espacio al final para escribir lo que quieras agregar.',
    1
);

-- Obtener ID de la encuesta
SET @encuesta_id = LAST_INSERT_ID();

-- =============================================
-- SECCIÓN 0: DATOS DEMOGRÁFICOS
-- =============================================
INSERT INTO secciones (encuesta_id, numero, titulo, descripcion, orden)
VALUES (@encuesta_id, 0, 'Datos Básicos', 'Información demográfica básica', 0);

SET @seccion_id = LAST_INSERT_ID();

-- Pregunta: Sexo
INSERT INTO preguntas (seccion_id, codigo, texto, tipo, requerida, orden)
VALUES (@seccion_id, 'sexo', '¿Cuál es tu sexo?', 'radio', 1, 1);

SET @pregunta_id = LAST_INSERT_ID();

INSERT INTO opciones (pregunta_id, valor, texto, orden) VALUES
(@pregunta_id, 'masculino', 'Masculino', 1),
(@pregunta_id, 'femenino', 'Femenino', 2),
(@pregunta_id, 'otro', 'Otro', 3);

-- Pregunta: Edad
INSERT INTO preguntas (seccion_id, codigo, texto, tipo, requerida, orden, config)
VALUES (@seccion_id, 'edad', '¿Cuál es tu edad?', 'number', 1, 2, '{"min": 18, "max": 100, "placeholder": "Ingresá tu edad"}');

-- =============================================
-- SECCIÓN 1: CASOS DE VIOLENCIA CONOCIDOS
-- =============================================
INSERT INTO secciones (encuesta_id, numero, titulo, descripcion, orden)
VALUES (@encuesta_id, 1, 'Casos de Violencia que Conocés', 'Se refiere a casos que hayas visto, escuchado o sabido que sucedieron con otras mujeres detenidas.\n\n<strong>Definiciones:</strong>\n<ul><li><strong>Golpes/fuerza física:</strong> Cualquier agresión con manos, objetos o mediante restricciones que cause dolor o daño corporal.</li><li><strong>Abuso sexual:</strong> Tocamientos, caricias o penetración no consentida, así como comentarios o insinuaciones sexuales que generen incomodidad.</li><li><strong>Requisa vejatoria:</strong> Búsqueda corporal o de pertenencias realizada de forma humillante, sin respeto por tu intimidad o género.</li><li><strong>Violencia verbal:</strong> Insultos, gritos, amenazas o comentarios despectivos por tu condición de detenida, género u otras características.</li></ul>', 1);

SET @seccion_id = LAST_INSERT_ID();

-- Pregunta 1: Golpes o fuerza física
INSERT INTO preguntas (seccion_id, codigo, texto, tipo, requerida, orden)
VALUES (@seccion_id, 'violencia_fisica_conocida', '¿Conocés algún caso de golpes o uso de fuerza física por parte del personal penitenciario contra una mujer privada de libertad?', 'radio', 0, 1);

SET @pregunta_id = LAST_INSERT_ID();

INSERT INTO opciones (pregunta_id, valor, texto, orden) VALUES
(@pregunta_id, '0', 'No conozco ningún caso', 1),
(@pregunta_id, '1-3', 'Conozco de 1 a 3 casos', 2),
(@pregunta_id, '4-7', 'Conozco de 4 a 7 casos', 3),
(@pregunta_id, '7+', 'Conozco más de 7 casos', 4);

-- Pregunta 2: Abuso sexual
INSERT INTO preguntas (seccion_id, codigo, texto, tipo, requerida, orden)
VALUES (@seccion_id, 'abuso_sexual_conocido', '¿Conocés algún caso de abuso sexual (tocamientos o penetración no consentida) por parte del personal penitenciario contra una mujer privada de libertad?', 'radio', 0, 2);

SET @pregunta_id = LAST_INSERT_ID();

INSERT INTO opciones (pregunta_id, valor, texto, orden) VALUES
(@pregunta_id, '0', 'No conozco ningún caso', 1),
(@pregunta_id, '1-3', 'Conozco de 1 a 3 casos', 2),
(@pregunta_id, '4-7', 'Conozco de 4 a 7 casos', 3),
(@pregunta_id, '7+', 'Conozco más de 7 casos', 4);

-- Pregunta 3: Requisa vejatoria
INSERT INTO preguntas (seccion_id, codigo, texto, tipo, requerida, orden)
VALUES (@seccion_id, 'requisa_vejatoria_conocida', '¿Conocés algún caso de requisa vejatoria por parte del personal penitenciario contra una mujer privada de libertad?', 'radio', 0, 3);

SET @pregunta_id = LAST_INSERT_ID();

INSERT INTO opciones (pregunta_id, valor, texto, orden) VALUES
(@pregunta_id, '0', 'No conozco ningún caso', 1),
(@pregunta_id, '1-3', 'Conozco de 1 a 3 casos', 2),
(@pregunta_id, '4-7', 'Conozco de 4 a 7 casos', 3),
(@pregunta_id, '7+', 'Conozco más de 7 casos', 4);

-- Pregunta 4: Violencia verbal
INSERT INTO preguntas (seccion_id, codigo, texto, tipo, requerida, orden)
VALUES (@seccion_id, 'violencia_verbal_conocida', '¿Conocés algún caso de violencia verbal por parte del personal penitenciario contra una mujer privada de libertad?', 'radio', 0, 4);

SET @pregunta_id = LAST_INSERT_ID();

INSERT INTO opciones (pregunta_id, valor, texto, orden) VALUES
(@pregunta_id, '0', 'No conozco ningún caso', 1),
(@pregunta_id, '1-3', 'Conozco de 1 a 3 casos', 2),
(@pregunta_id, '4-7', 'Conozco de 4 a 7 casos', 3),
(@pregunta_id, '7+', 'Conozco más de 7 casos', 4);

-- Pregunta 5: Violencia sufrida personalmente
INSERT INTO preguntas (seccion_id, codigo, texto, tipo, requerida, orden)
VALUES (@seccion_id, 'violencia_sufrida', '¿Has sufrido alguna de estas situaciones violentas por parte del personal penitenciario?', 'checkbox', 0, 5);

SET @pregunta_id = LAST_INSERT_ID();

INSERT INTO opciones (pregunta_id, valor, texto, orden) VALUES
(@pregunta_id, 'ninguna', 'No, nunca he sufrido ninguna', 1),
(@pregunta_id, 'verbal', 'Sí, he sufrido violencia verbal', 2),
(@pregunta_id, 'requisa', 'Sí, he sufrido requisa vejatoria', 3),
(@pregunta_id, 'fisica', 'Sí, he sufrido golpes o fuerza física', 4),
(@pregunta_id, 'sexual', 'Sí, he sufrido abuso sexual', 5),
(@pregunta_id, 'multiple', 'Sí, he sufrido más de una de estas situaciones', 6);

-- Pregunta 5b: Especificar (opcional)
INSERT INTO preguntas (seccion_id, codigo, texto, tipo, requerida, orden, config)
VALUES (@seccion_id, 'violencia_sufrida_detalle', 'Si respondiste "Sí" en la pregunta anterior, podés especificar cuál(es) si querés (no es obligatorio):', 'textarea', 0, 6, '{"placeholder": "Escribí acá si querés dar más detalles..."}');

-- =============================================
-- SECCIÓN 2: MEDIDAS PARA PREVENIR LA VIOLENCIA
-- =============================================
INSERT INTO secciones (encuesta_id, numero, titulo, descripcion, orden)
VALUES (@encuesta_id, 2, 'Medidas para Prevenir la Violencia', 'Podés marcar más de una opción', 2);

SET @seccion_id = LAST_INSERT_ID();

-- Pregunta 6: Medidas útiles
INSERT INTO preguntas (seccion_id, codigo, texto, tipo, requerida, orden)
VALUES (@seccion_id, 'medidas_prevencion', '¿Qué medidas considerás más útiles para prevenir la violencia por parte del personal penitenciario?', 'checkbox', 0, 1);

SET @pregunta_id = LAST_INSERT_ID();

INSERT INTO opciones (pregunta_id, valor, texto, orden, permite_texto_adicional) VALUES
(@pregunta_id, 'linea_telefonica', 'Línea telefónica exclusiva y segura para denuncias de detenidas', 1, 0),
(@pregunta_id, 'remocion_agresor', 'Medidas inmediatas de remoción del personal agresor del centro', 2, 0),
(@pregunta_id, 'asistencia_interdisciplinaria', 'Asistencia de equipos interdisciplinarios (psicólogos, trabajadores sociales, médicos) para las víctimas', 3, 0),
(@pregunta_id, 'talleres_personal', 'Talleres de capacitación en género y derechos humanos para todo el personal penitenciario', 4, 0),
(@pregunta_id, 'talleres_detenidas', 'Talleres de empoderamiento y conocimientos sobre derechos para las mujeres detenidas', 5, 0),
(@pregunta_id, 'otro', 'Otro', 6, 1);

-- Espacio adicional sección 2
INSERT INTO preguntas (seccion_id, codigo, texto, tipo, requerida, orden, config)
VALUES (@seccion_id, 'comentarios_seccion2', 'Si tenés algo más que querés decir sobre este tema, escribilo acá:', 'textarea', 0, 2, '{"placeholder": "Escribí acá tus comentarios..."}');

-- =============================================
-- SECCIÓN 3: CONDICIONES DE VIDA
-- =============================================
INSERT INTO secciones (encuesta_id, numero, titulo, descripcion, orden)
VALUES (@encuesta_id, 3, 'Condiciones de Vida', NULL, 3);

SET @seccion_id = LAST_INSERT_ID();

-- Pregunta 7: Condiciones básicas
INSERT INTO preguntas (seccion_id, codigo, texto, tipo, requerida, orden)
VALUES (@seccion_id, 'condiciones_basicas', '¿Considerás que las instalaciones donde vivís tienen las condiciones básicas necesarias?', 'radio', 0, 1);

SET @pregunta_id = LAST_INSERT_ID();

INSERT INTO opciones (pregunta_id, valor, texto, orden) VALUES
(@pregunta_id, 'si', 'Sí, todo está bien', 1),
(@pregunta_id, 'parcial', 'En parte: falta algo importante', 2),
(@pregunta_id, 'no', 'No, las condiciones son muy malas', 3);

-- Pregunta 8: Dificultades frecuentes
INSERT INTO preguntas (seccion_id, codigo, texto, tipo, requerida, orden)
VALUES (@seccion_id, 'dificultades', '¿Qué de estas dificultades experimentás con frecuencia?', 'checkbox', 0, 2);

SET @pregunta_id = LAST_INSERT_ID();

INSERT INTO opciones (pregunta_id, valor, texto, orden) VALUES
(@pregunta_id, 'hacinamiento', 'Hacinamiento en la celda', 1),
(@pregunta_id, 'agua', 'Falta de agua potable o limpia', 2),
(@pregunta_id, 'bano', 'Instalaciones de baño sucias o en mal estado', 3),
(@pregunta_id, 'ropa_aseo', 'Falta de ropa o productos de aseo', 4),
(@pregunta_id, 'alimentacion', 'Alimentación insuficiente o de mala calidad', 5),
(@pregunta_id, 'ninguna', 'Ninguna de las anteriores', 6);

-- Pregunta 9: Espacios al aire libre
INSERT INTO preguntas (seccion_id, codigo, texto, tipo, requerida, orden)
VALUES (@seccion_id, 'espacios_aire_libre', '¿Tenés acceso a espacios para moverte, hacer actividad física o descansar al aire libre?', 'radio', 0, 3);

SET @pregunta_id = LAST_INSERT_ID();

INSERT INTO opciones (pregunta_id, valor, texto, orden) VALUES
(@pregunta_id, 'siempre', 'Sí, siempre que necesite', 1),
(@pregunta_id, 'aveces', 'Solo algunas veces', 2),
(@pregunta_id, 'nunca', 'No, nunca', 3);

-- =============================================
-- SECCIÓN 4: ATENCIÓN A SALUD Y BIENESTAR
-- =============================================
INSERT INTO secciones (encuesta_id, numero, titulo, descripcion, orden)
VALUES (@encuesta_id, 4, 'Atención a tu Salud y Bienestar', NULL, 4);

SET @seccion_id = LAST_INSERT_ID();

-- Pregunta 10: Acceso atención médica
INSERT INTO preguntas (seccion_id, codigo, texto, tipo, requerida, orden)
VALUES (@seccion_id, 'atencion_medica', '¿Pudiste acceder a atención médica cuando lo necesitaste?', 'radio', 0, 1);

SET @pregunta_id = LAST_INSERT_ID();

INSERT INTO opciones (pregunta_id, valor, texto, orden) VALUES
(@pregunta_id, 'si', 'Sí, sin problemas', 1),
(@pregunta_id, 'dificil', 'Tuve que esperar mucho o no me atendieron bien', 2),
(@pregunta_id, 'no', 'No me permitieron acceder', 3);

-- Pregunta 11: Salud femenina
INSERT INTO preguntas (seccion_id, codigo, texto, tipo, requerida, orden)
VALUES (@seccion_id, 'salud_femenina', '¿Recibís atención diferenciada para tus necesidades de salud femenina? (ej: controles ginecológicos, embarazo, lactancia)', 'radio', 0, 2);

SET @pregunta_id = LAST_INSERT_ID();

INSERT INTO opciones (pregunta_id, valor, texto, orden) VALUES
(@pregunta_id, 'siempre', 'Sí, siempre', 1),
(@pregunta_id, 'aveces', 'Solo en algunos casos', 2),
(@pregunta_id, 'nunca', 'No, nunca', 3);

-- Pregunta 12: Apoyo psicológico
INSERT INTO preguntas (seccion_id, codigo, texto, tipo, requerida, orden)
VALUES (@seccion_id, 'apoyo_psicologico', '¿Tienes acceso a apoyo psicológico o emocional si lo necesitas?', 'radio', 0, 3);

SET @pregunta_id = LAST_INSERT_ID();

INSERT INTO opciones (pregunta_id, valor, texto, orden) VALUES
(@pregunta_id, 'si', 'Sí', 1),
(@pregunta_id, 'dificil', 'Solo si pido mucho', 2),
(@pregunta_id, 'no_quisiera', 'No, y me gustaría tenerlo', 3),
(@pregunta_id, 'no_necesito', 'No, no lo necesito', 4);

-- =============================================
-- SECCIÓN 5: TRATAMIENTO Y DIGNIDAD
-- =============================================
INSERT INTO secciones (encuesta_id, numero, titulo, descripcion, orden)
VALUES (@encuesta_id, 5, 'Tratamiento y Dignidad', NULL, 5);

SET @seccion_id = LAST_INSERT_ID();

-- Pregunta 13: Trato respetuoso
INSERT INTO preguntas (seccion_id, codigo, texto, tipo, requerida, orden)
VALUES (@seccion_id, 'trato_respetuoso', '¿Has sentido que el personal del centro te trata con respeto?', 'radio', 0, 1);

SET @pregunta_id = LAST_INSERT_ID();

INSERT INTO opciones (pregunta_id, valor, texto, orden) VALUES
(@pregunta_id, 'siempre', 'Siempre', 1),
(@pregunta_id, 'aveces', 'Algunas veces sí, otras no', 2),
(@pregunta_id, 'nunca', 'Nunca', 3);

-- Pregunta 14: Situaciones presenciadas
INSERT INTO preguntas (seccion_id, codigo, texto, tipo, requerida, orden)
VALUES (@seccion_id, 'situaciones_presenciadas', '¿Has sufrido o presenciado alguna de estas situaciones en el centro?', 'checkbox', 0, 2);

SET @pregunta_id = LAST_INSERT_ID();

INSERT INTO opciones (pregunta_id, valor, texto, orden) VALUES
(@pregunta_id, 'insultos', 'Insultos o humillaciones por tu género o condición', 1),
(@pregunta_id, 'requisas', 'Requisas humillantes', 2),
(@pregunta_id, 'aislamiento', 'Aislamiento arbitrario', 3),
(@pregunta_id, 'acoso', 'Acoso físico o sexual', 4),
(@pregunta_id, 'amenazas', 'Amenazas o represalias por hablar de tus derechos', 5),
(@pregunta_id, 'ninguna', 'Ninguna de las anteriores', 6);

-- Pregunta 15: Contacto familiar
INSERT INTO preguntas (seccion_id, codigo, texto, tipo, requerida, orden)
VALUES (@seccion_id, 'contacto_familiar', '¿Tienes facilidad para mantener contacto con tu familia y tus hijos?', 'radio', 0, 3);

SET @pregunta_id = LAST_INSERT_ID();

INSERT INTO opciones (pregunta_id, valor, texto, orden) VALUES
(@pregunta_id, 'si', 'Sí, las visitas son dignas y frecuentes', 1),
(@pregunta_id, 'limitadas', 'Las visitas están limitadas o en malas condiciones', 2),
(@pregunta_id, 'no', 'No puedo ver a mi familia', 3),
(@pregunta_id, 'no_familia', 'No tengo familia que me visite', 4);

-- =============================================
-- SECCIÓN 6: OPORTUNIDADES Y EMPODERAMIENTO
-- =============================================
INSERT INTO secciones (encuesta_id, numero, titulo, descripcion, orden)
VALUES (@encuesta_id, 6, 'Oportunidades y Empoderamiento', NULL, 6);

SET @seccion_id = LAST_INSERT_ID();

-- Pregunta 16: Acceso actividades
INSERT INTO preguntas (seccion_id, codigo, texto, tipo, requerida, orden)
VALUES (@seccion_id, 'acceso_actividades', '¿Tenés acceso a actividades como educación, talleres o trabajo dentro del centro?', 'radio', 0, 1);

SET @pregunta_id = LAST_INSERT_ID();

INSERT INTO opciones (pregunta_id, valor, texto, orden) VALUES
(@pregunta_id, 'varias', 'Sí, varias opciones', 1),
(@pregunta_id, 'alguna', 'Solo alguna actividad', 2),
(@pregunta_id, 'ninguna', 'No hay ninguna oportunidad', 3);

-- Pregunta 17: Interés en programas
INSERT INTO preguntas (seccion_id, codigo, texto, tipo, requerida, orden)
VALUES (@seccion_id, 'interes_programas', '¿Te gustaría participar en programas de capacitación o apoyo para tu reinserción social?', 'radio', 0, 2);

SET @pregunta_id = LAST_INSERT_ID();

INSERT INTO opciones (pregunta_id, valor, texto, orden) VALUES
(@pregunta_id, 'si', 'Sí, me interesa mucho', 1),
(@pregunta_id, 'depende', 'Depende de qué programa sea', 2),
(@pregunta_id, 'no', 'No, no me interesa', 3);

-- =============================================
-- SECCIÓN 7: ESPACIO LIBRE
-- =============================================
INSERT INTO secciones (encuesta_id, numero, titulo, descripcion, orden)
VALUES (@encuesta_id, 7, 'Espacio para Decir lo que Querés', NULL, 7);

SET @seccion_id = LAST_INSERT_ID();

-- Campo de texto libre final
INSERT INTO preguntas (seccion_id, codigo, texto, tipo, requerida, orden, config)
VALUES (@seccion_id, 'comentarios_finales', 'Escribí acá todo lo que quieras contar sobre tu experiencia, tus necesidades o lo que crees que debería cambiarse:', 'textarea', 0, 1, '{"placeholder": "Tu voz es importante. Contanos lo que quieras...", "rows": 6}');

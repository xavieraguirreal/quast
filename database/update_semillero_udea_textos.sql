-- =============================================
-- QUAST - Update textos literales encuesta Semillero UdeA
-- Sincroniza con el formulario original de Google
-- (excepto Seccion 0 demografica que es nuestra)
-- Fecha: 2026-04-11
-- =============================================

USE verumax_quast;

SET @encuesta_id = (
    SELECT id FROM encuestas
    WHERE tenant_slug = 'semillero-udea' AND codigo = 'el-espejo-invertido'
);

-- =============================================
-- DESCRIPCION E INSTRUCCIONES (literales del form)
-- =============================================
UPDATE encuestas
SET descripcion = 'Desarrollada por los estudiantes Hary Sacro y Luis Triana, participantes del Semillero de Penitenciario y Derechos Humanos de la Universidad de Antioquia. Nuestro objetivo es comprender las dinámicas de violencia horizontal en los centros de reclusión femeninos en Colombia, analizando cómo las estructuras de exclusión moldean las relaciones internas.',
    instrucciones = 'La información aquí suministrada será tratada con absoluta confidencialidad y anonimato. Los resultados se presentarán exclusivamente de manera agregada y general, priorizando el análisis académico del fenómeno y el rigor sociológico. En ningún caso se comprometerá la identidad de las participantes, sus testimonios individuales, ni el nombre de las organizaciones colaboradoras.\n\nSu participación es voluntaria y fundamental para construir una visión crítica y humana sobre la realidad penitenciaria de nuestro país.\n\nEsta investigación se realiza con la colaboración de la Universidad Liberté.\n\n¡Gracias por su tiempo y su voz!'
WHERE id = @encuesta_id;

-- =============================================
-- HEADERS DE SECCIONES (literales del form)
-- Seccion 0 es nuestra; secciones 1-4 son del form original.
-- =============================================
UPDATE secciones SET descripcion = 'Información básica para contextualizar las respuestas. Es anónima.'
WHERE encuesta_id = @encuesta_id AND numero = 0;

UPDATE secciones SET descripcion = 'En este primer bloque, queremos conocer cómo ha sido su proceso de adaptación a este entorno. No hay respuestas correctas o incorrectas; nos interesa su vivencia personal sobre cómo el sistema y las expectativas sociales influyen en su identidad y en las decisiones que debe tomar cada día.'
WHERE encuesta_id = @encuesta_id AND numero = 1;

UPDATE secciones SET descripcion = 'Esta sección busca explorar cómo nos relacionamos con los demás dentro de estos espacios. A veces, el entorno nos lleva a actuar de formas que no imaginábamos. Su mirada es fundamental para comprender cómo las historias que traemos de afuera y las dinámicas del patio se encuentran y transforman nuestros vínculos.'
WHERE encuesta_id = @encuesta_id AND numero = 2;

UPDATE secciones SET descripcion = 'Tus respuestas nos ayudan a visibilizar lo invisible y a construir un espacio donde la dignidad no dependa de cumplir expectativas ajenas, sino de tu propio valor como ser humano.'
WHERE encuesta_id = @encuesta_id AND numero = 3;

UPDATE secciones SET descripcion = 'Finalmente, queremos conversar sobre la voluntad y la fuerza necesaria para no dejarse anular por el sistema. Nos interesa identificar no solo las dificultades, sino también esos pequeños gestos de cuidado y solidaridad que ustedes construyen y que logran desafiar las lógicas de violencia.'
WHERE encuesta_id = @encuesta_id AND numero = 4;

-- =============================================
-- SECCION 0: PAIS (lista de hispanohablantes + España + EEUU + Canadá)
-- Texto literal del form original.
-- =============================================
UPDATE preguntas p
JOIN secciones s ON p.seccion_id = s.id
SET p.texto = 'Por favor selecciona el país en el que estás o estuviste privada de la libertad.',
    p.requerida = 1
WHERE s.encuesta_id = @encuesta_id AND p.codigo = 'pais';

SET @pregunta_pais_id = (
    SELECT p.id FROM preguntas p
    JOIN secciones s ON p.seccion_id = s.id
    WHERE s.encuesta_id = @encuesta_id AND p.codigo = 'pais'
);

DELETE FROM opciones WHERE pregunta_id = @pregunta_pais_id;

INSERT INTO opciones (pregunta_id, valor, texto, orden) VALUES
(@pregunta_pais_id, 'argentina', 'Argentina', 1),
(@pregunta_pais_id, 'bolivia', 'Bolivia', 2),
(@pregunta_pais_id, 'brasil', 'Brasil', 3),
(@pregunta_pais_id, 'canada', 'Canadá', 4),
(@pregunta_pais_id, 'chile', 'Chile', 5),
(@pregunta_pais_id, 'colombia', 'Colombia', 6),
(@pregunta_pais_id, 'costa-rica', 'Costa Rica', 7),
(@pregunta_pais_id, 'cuba', 'Cuba', 8),
(@pregunta_pais_id, 'ecuador', 'Ecuador', 9),
(@pregunta_pais_id, 'el-salvador', 'El Salvador', 10),
(@pregunta_pais_id, 'espana', 'España', 11),
(@pregunta_pais_id, 'estados-unidos', 'Estados Unidos', 12),
(@pregunta_pais_id, 'guatemala', 'Guatemala', 13),
(@pregunta_pais_id, 'guinea-ecuatorial', 'Guinea Ecuatorial', 14),
(@pregunta_pais_id, 'honduras', 'Honduras', 15),
(@pregunta_pais_id, 'mexico', 'México', 16),
(@pregunta_pais_id, 'nicaragua', 'Nicaragua', 17),
(@pregunta_pais_id, 'panama', 'Panamá', 18),
(@pregunta_pais_id, 'paraguay', 'Paraguay', 19),
(@pregunta_pais_id, 'peru', 'Perú', 20),
(@pregunta_pais_id, 'puerto-rico', 'Puerto Rico', 21),
(@pregunta_pais_id, 'republica-dominicana', 'República Dominicana', 22),
(@pregunta_pais_id, 'uruguay', 'Uruguay', 23),
(@pregunta_pais_id, 'venezuela', 'Venezuela', 24),
(@pregunta_pais_id, 'otros', 'Otros', 25);

-- =============================================
-- SECCION 1: ADAPTACION (Q2, Q3, Q4 obligatorias)
-- =============================================
UPDATE preguntas p JOIN secciones s ON p.seccion_id = s.id
SET p.texto = 'En el entorno social externo existen expectativas sobre el ''deber ser'' de las mujeres (como cuidadoras, madres o esposas). Dentro del contexto de reclusión, ¿Qué comportamientos o actitudes siente usted que el sistema o el entorno premian o protegen?',
    p.requerida = 1
WHERE s.encuesta_id = @encuesta_id AND p.codigo = 'adaptacion_premios';

UPDATE preguntas p JOIN secciones s ON p.seccion_id = s.id
SET p.texto = 'Entre la exigencia de sumisión que a veces se vive en libertad y las dinámicas de jerarquía que se presentan en el patio, ¿Cuál de estos entornos ha demandado un mayor esfuerzo de adaptación para usted?',
    p.requerida = 1
WHERE s.encuesta_id = @encuesta_id AND p.codigo = 'adaptacion_esfuerzo';

UPDATE preguntas p JOIN secciones s ON p.seccion_id = s.id
SET p.texto = '¿En qué momento considera que el proceso de reclusión deja de centrarse en el hecho jurídico y empieza a incidir directamente en su identidad como mujer, obligándola a modificar o fortalecer rasgos de su personalidad?',
    p.requerida = 1
WHERE s.encuesta_id = @encuesta_id AND p.codigo = 'adaptacion_identidad';

-- =============================================
-- SECCION 2: VINCULOS Y RELACIONES (Q5, Q6, Q7 OBLIGATORIAS)
-- =============================================
UPDATE preguntas p JOIN secciones s ON p.seccion_id = s.id
SET p.texto = '¿Cómo percibe usted que el entorno de reclusión influye en la formación de vínculos de solidaridad o, por el contrario, en la aparición de conflictos entre compañeras?',
    p.requerida = 1
WHERE s.encuesta_id = @encuesta_id AND p.codigo = 'vinculos_solidaridad';

UPDATE preguntas p JOIN secciones s ON p.seccion_id = s.id
SET p.texto = '¿Ha percibido situaciones en las que el entorno parece incentivar que unas personas asuman roles de control o vigilancia sobre otras? ¿Cómo explicaría que alguien vulnerada fuera del sistema, termine ejerciendo roles de autoridad sobre sus pares adentro?',
    p.requerida = 1
WHERE s.encuesta_id = @encuesta_id AND p.codigo = 'vinculos_control';

UPDATE preguntas p JOIN secciones s ON p.seccion_id = s.id
SET p.texto = '¿De qué manera cree usted que las violencias de género vividas antes de ingresar a este espacio influyen en la forma en que se relaciona hoy con sus compañeras?',
    p.requerida = 1
WHERE s.encuesta_id = @encuesta_id AND p.codigo = 'vinculos_violencias_previas';

-- =============================================
-- SECCION 3: TIPOS DE VIOLENCIA (Q8 obligatoria + opciones literales + Otros)
-- =============================================
UPDATE preguntas p JOIN secciones s ON p.seccion_id = s.id
SET p.texto = '¿Qué tipos de violencia ha experimentado en el sistema penitenciario por no ajustarse a los roles o expectativas sociales tradicionalmente asignados a las mujeres? (Puede seleccionar varias)',
    p.requerida = 1
WHERE s.encuesta_id = @encuesta_id AND p.codigo = 'tipos_violencia';

SET @pregunta_violencia_id = (
    SELECT p.id FROM preguntas p
    JOIN secciones s ON p.seccion_id = s.id
    WHERE s.encuesta_id = @encuesta_id AND p.codigo = 'tipos_violencia'
);

DELETE FROM opciones WHERE pregunta_id = @pregunta_violencia_id;

INSERT INTO opciones (pregunta_id, valor, texto, orden, permite_texto_adicional) VALUES
(@pregunta_violencia_id, 'verbal_estereotipos', 'Violencia verbal basada en estereotipos', 1, 0),
(@pregunta_violencia_id, 'verbal_prejuicios', 'Violencia verbal basada en prejuicios (expectativas sobre su "rol" en la sociedad)', 2, 0),
(@pregunta_violencia_id, 'agresiones_fisicas', 'Agresiones físicas', 3, 0),
(@pregunta_violencia_id, 'psicologicas', 'Violencias psicológicas', 4, 0),
(@pregunta_violencia_id, 'homofobia', 'Homofobia', 5, 0),
(@pregunta_violencia_id, 'transfobia', 'Transfobia', 6, 0),
(@pregunta_violencia_id, 'dominacion_emocional', 'Dominación emocional', 7, 0),
(@pregunta_violencia_id, 'rechazo_delitos', 'Rechazo por delitos específicos', 8, 0),
(@pregunta_violencia_id, 'dominacion_economica', 'Dominación económica', 9, 0),
(@pregunta_violencia_id, 'aislamiento_custodia', 'Aislamiento ejercido por cuerpo de custodia y vigilancia', 10, 0),
(@pregunta_violencia_id, 'aislamiento_social', 'Aislamiento social', 11, 0),
(@pregunta_violencia_id, 'discursos_machistas', 'Reproducción de discursos machistas o misóginos entre pares', 12, 0),
(@pregunta_violencia_id, 'normalizacion_insultos', 'Normalización de insultos de género', 13, 0),
(@pregunta_violencia_id, 'roles_desiguales', 'Reforzamiento de roles desiguales incluso en actividades colectivas', 14, 0),
(@pregunta_violencia_id, 'dependencia_forzada', 'Dependencia forzada que limita la autonomía personal', 15, 0),
(@pregunta_violencia_id, 'otros', 'Otros', 16, 1);

-- =============================================
-- SECCION 4: RESISTENCIA Y SOLIDARIDAD
-- Q9, Q10, Q11 OBLIGATORIAS, Q12 OPCIONAL
-- =============================================
UPDATE preguntas p JOIN secciones s ON p.seccion_id = s.id
SET p.texto = '¿Ha sentido que mostrar vulnerabilidad es un riesgo frente a la institución o frente al grupo? La adopción de actitudes dominantes o defensivas, ¿la percibe como una decisión individual o como una respuesta mecánica a las exigencias de la jerarquía?',
    p.requerida = 1
WHERE s.encuesta_id = @encuesta_id AND p.codigo = 'resistencia_vulnerabilidad';

UPDATE preguntas p JOIN secciones s ON p.seccion_id = s.id
SET p.texto = 'En la balanza de su cotidianidad, ¿qué tanto espacio queda para la voluntad propia frente a las reacciones necesarias para que el sistema no anule su identidad? ¿Se castiga más severamente en el patio a quienes deciden no cumplir con roles de fuerza?',
    p.requerida = 1
WHERE s.encuesta_id = @encuesta_id AND p.codigo = 'resistencia_voluntad';

UPDATE preguntas p JOIN secciones s ON p.seccion_id = s.id
SET p.texto = 'Fuera de las dinámicas de agresión, ¿qué actos de cuidado o apoyo mutuo ha observado que logren desafiar la lógica de violencia del sistema? ¿Qué otras formas de respeto o reconocimiento ha logrado identificar o construir?',
    p.requerida = 1
WHERE s.encuesta_id = @encuesta_id AND p.codigo = 'resistencia_cuidado';

UPDATE preguntas p JOIN secciones s ON p.seccion_id = s.id
SET p.texto = 'Si desea agregar algo más lo puede escribir a continuación.',
    p.requerida = 0
WHERE s.encuesta_id = @encuesta_id AND p.codigo = 'comentarios_finales';

-- Actualizar pregunta 8 para agregar "(Podés marcar más de una)"
UPDATE preguntas
SET texto = '¿Qué de estas dificultades experimentás con frecuencia? (Podés marcar más de una)'
WHERE codigo = 'dificultades';

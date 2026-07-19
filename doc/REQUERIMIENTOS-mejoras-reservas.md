# Levantamiento de requerimientos — Mejoras al flujo de reservas

> **Objetivo del documento:** guiar la conversación con el cliente para definir con
> precisión 3 mejoras solicitadas sobre el módulo de reservas de tours, **antes** de
> estimar y desarrollar. Cada punto está redactado en lenguaje de negocio, con
> ejemplos concretos y las opciones posibles. Al final de cada sección hay una tabla
> **"Decisión"** para dejar registrada la respuesta del cliente.
>
> Público: cliente (negocio) + equipo de desarrollo.
> Estado: **borrador para validar** — ningún cambio se ha implementado todavía.

---

## Contexto — cómo funciona hoy

Para que las preguntas tengan sentido, esto es lo que el sistema ya hace:

- Cada tour tiene **un precio por persona** (`price`), con un **precio de oferta**
  opcional (`sale_price`) que, si es menor, es el que se cobra.
- Al reservar, el total = `precio por persona × número de personas` + **servicios
  extra** opcionales (`service_fees`) que el admin define por tour (cargo fijo o por
  persona) y el cliente selecciona.
- El precio **siempre se calcula en el servidor**; el cliente nunca envía montos.
- El **punto de recogida existe hoy solo para reservas de transporte** (alquiler de
  vehículo), como texto libre. **Los tours no tienen punto de recogida.**
- Existe un campo `child_price` (precio infantil) en la base de datos, pero
  **actualmente no se usa** en el cálculo de la reserva.

Las 3 mejoras encajan sobre esta base. Las preguntas buscan cerrar los detalles que
hoy son ambiguos.

---

## Mejora 1 — Upgrade opcional de vehículo para grupos

**Lo que se pidió:** cuando la reserva alcance **5 o más personas**, ofrecer opciones
de vehículos más grandes mediante cargos adicionales (ej. **+$20** o **+$50**),
manteniendo el **precio base del tour**.

### Preguntas para el cliente

1. **Umbral del grupo.** ¿El upgrade se ofrece exactamente a partir de **5 personas**?
   ¿Ese número es fijo para todos los tours o debería poder configurarse por tour
   (unos tours a partir de 5, otros de 8, etc.)?

2. **Origen de las opciones.** ¿Cómo se definen los vehículos que se ofrecen?
   - **(A)** El administrador escribe libremente las opciones en cada tour: una
     etiqueta y un cargo (ej. *"Van 12 pasajeros — +$20"*, *"Minibús 18 — +$50"*).
     No se vinculan al inventario real de vehículos. *(Más simple, más flexible.)*
   - **(B)** Las opciones salen del **catálogo real de vehículos** que ya existe en el
     sistema (módulo de transporte), eligiendo cuáles aplican a cada tour y su cargo.
     *(Más complejo: hoy no existe ninguna relación tour ↔ vehículos; habría que
     crearla. A cambio, se reutiliza el inventario y su capacidad.)*

3. **Tipo de cargo.** El cargo (+$20, +$50) ¿es **fijo por reserva** (se suma una vez
   al total) o **por persona** (se multiplica por el número de personas)? Los ejemplos
   sugieren cargo fijo, conviene confirmarlo.

4. **Cantidad de opciones elegibles.** ¿El cliente elige **una sola** opción de
   vehículo (lo natural: viaja en un vehículo) o podría combinar varias?

5. **¿Opcional siempre?** ¿El upgrade es **siempre opcional**, o hay casos en que se
   vuelve **obligatorio** (por ejemplo, si el grupo supera la capacidad del vehículo
   estándar incluido)?

6. **Vehículo base.** ¿El precio del tour ya incluye un transporte "estándar" y estos
   son alternativas superiores, o el tour no incluye transporte y esto es un adicional
   suelto? Esto define cómo se explica al cliente final.

7. **Cargo variable por tamaño de grupo.** ¿El cargo de una misma opción puede cambiar
   según cuántos sean? (ej. *Van: +$20 para 5–8 personas, +$35 para 9–12*), o ¿es un
   monto único por opción?

8. **Capacidad / logística.** El upgrade ¿es **solo un cargo** o también debe reflejar
   capacidad (nº de asientos) y validar que el grupo quepa?

9. **Alcance.** ¿Aplica a **todos** los tours o solo a algunos que el admin marque?

10. **Reagendar / editar.** Si una reserva con upgrade cambia y el grupo baja de 5,
    ¿se retira el upgrade automáticamente? ¿Se recalcula el total?

11. **Visibilidad.** ¿El cargo del upgrade debe aparecer **desglosado** en el resumen,
    el correo de confirmación y el recibo/factura PDF?

| # | Decisión del cliente |
|---|---|
| Umbral (personas) | |
| Origen de opciones (A libre / B catálogo) | |
| Cargo (fijo / por persona) | |
| Una o varias opciones | |
| Opcional / obligatorio | |
| Cargo variable por grupo (sí/no) | |
| Alcance (todos / algunos tours) | |

---

## Mejora 2 — Precios escalonados por número de pasajeros

**Lo que se pidió:** tarifas más atractivas para grupos. Ejemplo dado: **1 persona
$85, 2 personas $70 por persona**, etc.

### La pregunta clave: ¿cómo se calcula?

Hay dos formas de interpretar "2 personas $70 por persona", y dan totales distintos.
Es la decisión más importante de esta mejora.

**Opción A — La tarifa del tramo aplica a TODO el grupo** *(la más común e intuitiva)*

Se busca el tramo según cuántos son y esa tarifa por persona se cobra a todos.

```
Tarifas: 1 pers = $85   |   2–4 pers = $70 c/u   |   5+ pers = $60 c/u

1 persona   →  1 × $85  = $85
3 personas  →  3 × $70  = $210
6 personas  →  6 × $60  = $360
```

**Opción B — Por bloques (como los impuestos)**

Cada rango cobra su tarifa solo a las personas que caen en ese rango.

```
3 personas →  $85 (la 1ª) + $70 + $70          = $225
6 personas →  $85 + 3×$70 + 2×$60 = 85+210+120 = $415
```

> La mayoría de operadores turísticos usan la **Opción A**. Confirmar cuál espera el
> cliente.

### Preguntas para el cliente

1. **Modelo de cálculo:** ¿Opción A (tarifa del tramo × todos) u Opción B (por
   bloques)?

2. **Tramos y tarifas exactas.** ¿Cuáles son los cortes y los precios? ¿Son **iguales
   para todos los tours** o **cada tour define los suyos**? (ej. este tour: 1=$85,
   2=$70, 5=$60; otro tour: distinto).

3. **Tope superior.** ¿Hay un límite (ej. *"10 o más, tarifa fija de $55"*)?

4. **Relación con la oferta actual (`sale_price`).** Hoy existe un precio de oferta.
   Cuando un tour tenga tramos, ¿los tramos **reemplazan** por completo al precio de
   oferta, o deben poder convivir (ej. oferta = descuento adicional sobre el tramo)?

5. **Precio infantil.** Existe un campo de precio para niños que hoy no se usa. ¿Debe
   entrar en juego con los tramos (adultos por tramo, niños con tarifa aparte) o lo
   dejamos fuera por ahora?

6. **Precio mostrado en el catálogo.** En la tarjeta del tour (listado), ¿qué precio se
   muestra? ¿*"desde $85"* (1 persona) o *"desde $60"* (el más bajo, para atraer
   grupos)?

7. **Alcance.** ¿Todos los tours pasan a tener tramos, o solo los que el admin
   configure? Los tours sin tramos seguirían con precio plano.

8. **Temporada.** ¿Los tramos cambian por fecha/temporada, o son fijos por ahora?

| # | Decisión del cliente |
|---|---|
| Modelo (A todos / B bloques) | |
| Tramos por tour o globales | |
| Tope superior | |
| Interacción con oferta (sale_price) | |
| Precio infantil (incluir/excluir) | |
| Precio de catálogo ("desde …") | |
| Alcance (todos / algunos) | |

---

## Mejora 3 — Punto de recogida en el flujo de reserva

**Lo que se pidió:** incorporar la **selección o captura** del punto de recogida en el
flujo de reserva y **mostrarlo en el resumen final**.

### Preguntas para el cliente

1. **Forma de captura.** ¿Cómo elige el cliente su punto de recogida?
   - **(A)** **Lista predefinida por tour** — el admin define los puntos válidos de cada
     tour y el cliente elige uno.
   - **(B)** **Texto libre** — el cliente escribe dónde quiere que lo recojan.
   - **(C)** **Ambos** — lista predefinida + opción *"Otro"* para escribir uno propio.

2. **Obligatorio u opcional.** ¿El cliente **debe** indicar punto de recogida para
   completar la reserva, o es opcional?

3. **Alcance de los puntos.** Si es lista: ¿los puntos se definen **por tour** o hay un
   **catálogo global** reutilizable entre tours?

4. **Datos de cada punto.** ¿Qué información lleva un punto de recogida?
   - Solo el nombre (ej. *"Plaza Central"*).
   - Nombre + dirección.
   - + ubicación en mapa (coordenadas).
   - + **hora estimada de recogida**.

5. **Impacto en precio/logística.** ¿El punto de recogida **afecta el precio** (algunos
   puntos cuestan más) o el horario? ¿O es solo informativo?

6. **Zona de cobertura.** ¿Hay que **validar** que el punto esté dentro de una zona
   permitida, o se acepta cualquiera?

7. **Recogida vs. retorno.** ¿Se necesita solo **punto de recogida**, o también
   **punto de retorno** (dropoff) al final del tour?

8. **Dónde se muestra.** Además del resumen antes de pagar, ¿debe aparecer en el
   **correo de confirmación**, el **recibo/factura PDF** y el **panel del admin**?

9. **Edición posterior.** ¿El cliente puede **cambiar** el punto de recogida después de
   reservar (hasta cierto momento)?

| # | Decisión del cliente |
|---|---|
| Forma (A lista / B libre / C ambos) | |
| Obligatorio / opcional | |
| Puntos por tour o catálogo global | |
| Datos por punto (nombre/dirección/mapa/hora) | |
| ¿Afecta precio u horario? | |
| ¿Solo recogida o también retorno? | |
| Dónde se muestra (resumen/correo/PDF/admin) | |

---

## Preguntas transversales (aplican a las 3 mejoras)

1. **¿Quién configura esto?** Se asume que el **administrador** define los tramos, las
   opciones de upgrade y los puntos de recogida desde el panel de cada tour. Confirmar
   — esto implica también trabajo de **pantallas de administración**, no solo del flujo
   público de reserva.

2. **¿Solo tours o también transporte?** Las 3 mejoras están redactadas para **tours**.
   ¿Alguna aplica también al módulo de **alquiler de transporte**?

3. **Moneda.** Cada tour ya maneja su moneda (`currency_code`). ¿Los nuevos montos
   (tramos, upgrades) van en la misma moneda del tour? (se asume que sí).

4. **Factura / recibo.** ¿Estos conceptos nuevos deben verse desglosados en la
   **factura y el recibo PDF** que ya existen?

5. **Reservas existentes.** Las reservas ya creadas ¿se quedan como están (el nuevo
   esquema aplica solo a reservas nuevas)? Se recomienda **sí** para no alterar
   históricos.

6. **Prioridad.** Si hay que entregar por partes, ¿en qué **orden** se quieren las 3
   mejoras?

7. **Frontend.** ¿El alcance incluye la **implementación visual** en la web (flujo de
   reserva + editor de tours del admin), o solo la **API**?

| # | Decisión del cliente |
|---|---|
| Quién configura (admin panel) | |
| Aplica a transporte también | |
| Desglose en factura/PDF | |
| Reservas existentes | |
| Prioridad de entrega | |
| Alcance (API / API + web) | |

---

## Resumen de decisiones a cerrar

Para poder estimar y desarrollar sin bloqueos, necesitamos cerrar como mínimo:

- [ ] **Upgrade:** umbral, origen de opciones (libre vs. catálogo), tipo de cargo.
- [ ] **Precios escalonados:** modelo de cálculo (A vs. B) y tabla de tramos.
- [ ] **Pickup:** forma de captura (lista / libre / ambos) y si es obligatorio.
- [ ] **Transversal:** alcance (solo API o también web), y quién configura.

Con estas respuestas se puede pasar a **diseño técnico y estimación**.

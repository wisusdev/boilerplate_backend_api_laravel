Desde el panel se gestiona todo el sitio: tours, reservas, pagos, facturas y textos. Este manual explica cada sección en el orden en que se usa. Si algo no funciona como se describe aquí, avisa al responsable técnico.

## Acceso y menú del panel

Entra en el panel con tu correo y contraseña. Solo ven el panel las cuentas con rol de equipo (superadmin, admin, finanzas, editor o guía); las cuentas de clientes no tienen acceso.

El menú de la izquierda muestra únicamente las secciones que tu rol permite usar:

| Grupo | Sección | Para qué sirve |
| --- | --- | --- |
| General | Dashboard | Cifras del mes en curso y accesos rápidos a las secciones |
| Administración | Usuarios · Roles | Cuentas del equipo y de clientes, y qué puede hacer cada rol |
| Catálogo | Tours · Renta de vehículos | Lo que se vende, con sus reservas y calendario |
| Catálogo | Categorías · Monedas · Cupones | Clasificación de tours, monedas aceptadas y descuentos |
| Catálogo | Mapa · Galería | Pines del mapa público y fotos de la portada |
| Operaciones | Pagos · Facturas (DTE) · Compras (DTE) | Cobros y documentos tributarios electrónicos |
| Operaciones | Finanzas · Mis gastos | Ingresos, gastos y rentabilidad |
| Operaciones | Consultas · Reseñas · Suscriptores | Mensajes de clientes, opiniones y lista de ofertas |
| Sistema | Configuración · Textos legales | Ajustes del sitio y términos, privacidad y cancelación |
| Sistema | Ayuda | Este manual |

"Compras (DTE)" solo aparece cuando la facturación electrónica está activada. Si acabas de recibir un rol nuevo y no ves una sección, cierra sesión y vuelve a entrar.

## Puesta en marcha

Antes de abrir las reservas al público hay que completar **Sistema → Configuración** y los textos legales. La configuración tiene seis pestañas y un solo botón **Guardar cambios**, que guarda todas a la vez.

1. **General → Identidad:** nombre del proyecto, eslogan y los dos logos. El claro va sobre fondos oscuros (portada y pie). El oscuro va sobre fondos claros (barra superior al bajar). Formatos JPG, PNG o WEBP de hasta 4 MB; aunque la pantalla menciona SVG, el servidor lo rechaza.
2. **General → Contacto:** correo público, correos que reciben los avisos de consultas (separados por coma), teléfono, WhatsApp con código de país y sin espacios (por ejemplo 50376607070), dirección, ciudad y país. El WhatsApp activa el botón "Pedir información por WhatsApp" del mapa.
3. **General → Sección Nosotros:** imagen del equipo, título, dos párrafos, número de guías y cuatro estadísticas. Lo que se deje vacío muestra el texto por defecto.
4. **Redes sociales:** direcciones de Facebook, Instagram, X, YouTube y TikTok.
5. **Login social** (opcional): activar Google o Facebook y pegar su Client ID o App ID. Los botones solo aparecen con el interruptor encendido y el ID puesto.
6. **Métodos de pago:** activar los que se ofrecen (efectivo, pago asistido por WhatsApp, enlace de pago del banco, Wompi) y elegir el diseño del PDF de factura (Clásica, Moderna o Minimalista). Ver la tabla de opciones debajo.
7. **Regional:** moneda, zona horaria, máximo de reservas por día (0 = sin límite) y la política de reserva: antelación mínima en días (solo tours) y ventana de cancelación en horas.
8. **Regional → Módulos:** encender o apagar la suscripción a ofertas (formulario del pie y ventana emergente).
9. **Factura electrónica:** solo si se va a facturar con el Ministerio de Hacienda. Se explica en su propia sección.
10. **Textos legales:** completar los datos pendientes y revisar con un abogado.

Opciones de los métodos de pago:

| Método | Qué se configura | Detalle importante |
| --- | --- | --- |
| Efectivo | Solo el interruptor | El pago queda pendiente hasta cobrarse |
| Pago asistido por WhatsApp | Número de WhatsApp de pagos | Vacío = se usa el WhatsApp de contacto |
| Enlace de pago del banco | Dominios autorizados, horas de validez (24 por defecto), mensaje al cliente, doble control, liberar asiento al caducar | Dominios por defecto: baccredomatic.com y credomatic.com |
| Wompi | Modo Sandbox o Producción, Audience, Client ID y Client Secret | Solo cobra en dólares; un secreto vacío conserva el guardado |

El correo saliente (SMTP) y los destinatarios de algunos avisos internos no se configuran en el panel: los define el técnico en el servidor.

## Catálogo

El catálogo es lo que el cliente ve y reserva: tours, vehículos, cupones, el mapa y la galería.

### Crear un tour

1. Ve a **Tours → Lista de tours** y pulsa **Nuevo tour**.
2. Completa la **información básica**: título, descripción y ubicación (obligatorios) y la categoría. Solo aparecen las categorías activas.
3. En la columna derecha pon el **precio por persona**, la duración en días y noches y la **capacidad máxima**. La moneda es la de Configuración y no se cambia aquí.
4. Rellena itinerario, destacados, qué incluye y qué no incluye: **un punto por línea**.
5. Escribe las preguntas frecuentes: la pregunta en la primera línea, la respuesta debajo, y separa cada pregunta con una línea que solo tenga `---`.
6. Configura las opciones de venta (tabla siguiente) y, si quieres, los marcadores del mapa del tour haciendo clic sobre él.
7. Marca **Tour activo** para publicarlo y **Destacado en la portada** si debe salir en el inicio. Guarda.
8. Vuelve a abrir el tour y pulsa **Gestionar imágenes**: las fotos solo se suben al editar, no al crear. La imagen principal se reemplaza; la galería admite varias a la vez. JPG, PNG o WEBP de hasta 10 MB.

| Opción del tour | Cómo funciona |
| --- | --- |
| Servicios extra | Nombre y monto, con precio fijo o por persona. El cliente los elige al reservar |
| Precios escalonados | Tramos "desde N personas" con un % de descuento. Se aplica el tramo más alto que el grupo alcanza |
| Opciones de vehículo | Hasta 3 vehículos con su recargo. El cliente siempre puede elegir "Sin vehículo" gratis |
| Secciones visibles en la reserva | Interruptores para mostrar u ocultar precio, vehículo, punto de recogida y cupón |
| SEO | Meta título y meta descripción (ideal hasta 160 caracteres) para Google y para las vistas previas de WhatsApp y Facebook. Vacíos = se usan el título y la descripción |

En la lista de tours la estrella destaca un tour y el ojo lo oculta o lo muestra sin borrarlo. Ocultar es mejor que eliminar: el tour conserva sus reservas y reseñas.

### Vehículos

En **Renta de vehículos → Vehículos → Nuevo vehículo**: nombre, tipo (Van, Bus, SUV, Sedan, Minibus, Motorcycle, Boat u Other), ubicación base, capacidad, características (una por línea), tarifa por hora y/o por día, SEO y **Vehículo activo**. Las imágenes se suben igual que en los tours.

### Cupones, categorías y monedas

| Sección | Qué se define | A tener en cuenta |
| --- | --- | --- |
| Cupones | Código, porcentaje (con tope en dólares) o monto fijo, a qué aplica (todo, tours o transporte), mínimo de personas y de monto, límite de usos total y por cliente, fechas de inicio y fin | Al cancelarse una reserva, el uso se devuelve. Borrar un cupón no afecta a las reservas que ya lo usaron |
| Categorías | Nombre y color | Una categoría desactivada deja de ofrecerse en el formulario de tour |
| Monedas | Código, nombre, símbolo, tasa a USD | Las reservas se cobran en la moneda de Configuración → Regional. Las monedas no se pueden borrar |

### Mapa

El mapa público de /mapa muestra pines con información de los lugares. En **Catálogo → Mapa → Nuevo pin**:

1. Escribe el **título** y una descripción breve.
2. Busca la **dirección** en el cuadro sobre el mapa (Enter o el botón) y ajusta el punto con un clic o arrastrando el marcador. La vista satélite ayuda a colocarlo con precisión.
3. Elige **icono** (20 opciones) y **color**.
4. Añade, si aplica, el **tour relacionado**, un **video de Instagram** (un post o reel público: pega su enlace), un **enlace** con el texto del botón y una **imagen**. Con video, se muestra el video en lugar de la imagen.
5. Deja marcado **Visible en el mapa** y guarda. El orden vacío lo coloca al final.

Si en Configuración hay un WhatsApp de contacto, cada pin muestra el botón "Pedir información por WhatsApp" con el mensaje ya escrito.

### Galería

Fotos de la portada: hasta 20 a la vez, JPG, PNG, WEBP o GIF de hasta 7 MB cada una, con descripción opcional.

## Reservas y cobros

Una reserva tiene tres estados: **pendiente** al crearse, **confirmada** cuando el equipo la acepta y **cancelada**. Confirmar es la acción clave: genera la factura interna, envía al cliente la confirmación con su comprobante PDF y, si está configurado, emite el DTE.

![Recorrido de una reserva: de pendiente a confirmada o cancelada](/ayuda/recorrido-reserva.svg)

El cliente solo cancela una reserva pendiente y fuera de la ventana de cancelación; una confirmada solo la cancela el equipo. Una reserva pagada por completo con el enlace del banco se confirma sola.

### Dónde se gestionan

| Menú | Pestaña | Qué se hace |
| --- | --- | --- |
| Tours | Tours agendados | Lista de reservas de tours con cliente, fecha, personas, total, estado y pago. **Ver** abre la ficha: cupón, vehículo, punto de recogida con enlace al mapa, forma de pago |
| Tours | Calendario | Las mismas reservas por día. Un clic en el día abre su detalle |
| Renta de vehículos | Reservas · Calendario | Reservas de transporte; el calendario es solo de consulta |

Para cambiar el estado abre la reserva y pulsa **Editar estado**. Usa solo Pendiente, Confirmada o Cancelada: la opción "Completada" da error al guardar y el campo de notas internas no se guarda.

### Registrar una reserva de un cliente (presencial o por teléfono)

1. En **Tours agendados** o en **Renta de vehículos → Reservas**, pulsa **Nueva reserva (presencial)**.
2. Escribe el **correo del cliente** (obligatorio), su nombre y teléfono. Si el correo ya tiene cuenta, la reserva se le asigna; si no, se le crea una cuenta sin enviarle correo.
3. Elige tour, fecha y personas (o vehículo, tipo por día u hora, horarios y lugares).
4. Marca **Confirmar la reserva de inmediato** si ya está acordada: el cliente recibe la confirmación.

No uses el botón "Nueva reserva" del Calendario para clientes: ese formulario no pide el cliente y deja la reserva a nombre de quien la crea. La reserva presencial tampoco admite cupón ni registrar el pago, y respeta el cupo, la antelación mínima y el máximo diario.

### Qué puede hacer el cliente

- **Cancelar** una reserva pendiente desde su perfil, salvo dentro de la ventana de cancelación (por ejemplo, 24 horas antes). Una reserva confirmada solo la cancela el equipo. Al cancelar se devuelve el uso del cupón y los administradores reciben un correo.
- **Cambiar la fecha** de una reserva no cancelada, si hay cupo. Se avisa por correo al cliente y al equipo.
- **Escribir un mensaje** sobre su reserva. Llega por correo a los administradores; el panel no tiene bandeja de mensajes, así que se responde por correo o WhatsApp.

Una reserva nueva no envía aviso al equipo: revisa **Tours agendados** a diario.

### Cobros

| Forma de pago | Qué pasa | Qué hace el equipo |
| --- | --- | --- |
| Efectivo | El pago queda pendiente | Cobrar en persona. El panel todavía no tiene un botón para marcar ese pago como cobrado |
| Wompi (tarjeta) | El pago queda cobrado automáticamente | **Confirmar la reserva a mano**: el cobro no la confirma solo |
| Pago asistido por WhatsApp | Se abre WhatsApp con el detalle de la reserva y el equipo recibe un correo | Atender al cliente por WhatsApp y cerrar el cobro |
| Enlace de pago del banco | El sistema pide al equipo que emita un enlace | Seguir los pasos siguientes; al confirmar el cobro total, la reserva se confirma sola |

El historial de todos los pagos está en **Operaciones → Pagos → Historial** (solo lectura).

### Enlace de pago del banco, paso a paso

1. El cliente elige pagar con enlace. El sistema crea una referencia (por ejemplo `CA-000123-A7K4`) y envía al equipo el correo "Hay que emitir un enlace de pago".
2. Crea el enlace en el portal del banco con esa referencia en la descripción.
3. En **Pagos → Enlaces de pago**, pulsa **Pegar enlace**, pega la dirección y pulsa **Guardar y enviar al cliente**. Solo se aceptan enlaces de los dominios autorizados.
4. Cuando el cliente paga, puede reportarlo con su número de autorización y comprobante; el equipo recibe un correo.
5. Verifica el cargo en el banco y pulsa **Confirmar**: número de autorización, importe cobrado y fecha del cargo. Si el importe es menor, el resto queda pendiente. Con el doble control activado, quien pegó el enlace no puede confirmarlo.

Otras acciones: **Reenviar**, ver el **Comprobante** del cliente, **Anular** o **Revertir** (con motivo; revertir devuelve el saldo a pendiente) y **Conciliar extracto** con el CSV del banco, que solo informa diferencias.

Los enlaces que vencen pasan a **Caducado** cada hora. Si en Configuración está activo "Liberar el asiento", la reserva pendiente se cancela y se avisa al cliente. Cada mañana a las 8:00 llega un resumen de los enlaces abiertos.

## Facturación electrónica (DTE)

La facturación electrónica con el Ministerio de Hacienda (MH) es opcional. Apagada, el sistema sigue creando facturas internas y PDF, pero no envía nada a Hacienda. Encendida, cada factura se firma, se envía al MH y queda con su sello de recepción.

### Activarla

En **Configuración → Factura Electrónica**:

1. Elige el **ambiente**: Prueba mientras se valida con Hacienda, Producción cuando el MH lo autorice.
2. Completa los **datos del emisor**: NIT, NRC, razón social, nombre comercial, actividad económica (79110 agencias de viajes o 79120 operadores turísticos), tipo y códigos de establecimiento y punto de venta, departamento, municipio, distrito, dirección, teléfono y correo.
3. Completa el **responsable del establecimiento**: firma las contingencias y las invalidaciones.
4. Escribe el **usuario y la contraseña de la API del MH** (usuario vacío = el NIT).
5. Sube el **certificado de firma** (.crt, .p12 o .pfx, hasta 2 MB) con su contraseña y pulsa **Cargar y validar certificado**.
6. Configura la **retención y percepción del IVA** si aplica: solo afecta al crédito fiscal. Por defecto, 1 % a partir de $100.
7. Enciende **Habilitar DTE** y, si quieres que cada reserva confirmada se facture sola, **Generar DTE automáticamente al confirmar**. Guarda.

Si falta o es inválido algún dato del emisor, el guardado falla y la pantalla indica cuál corregir.

### Documentos que emite el sistema

| Documento | Código | Cuándo se usa | Dónde |
| --- | --- | --- | --- |
| Factura | 01 | Venta a consumidor final | Facturas → Nueva factura, o al confirmar una reserva |
| Comprobante de crédito fiscal (CCF) | 03 | Venta a una empresa con NRC | Facturas → Nueva factura → Crédito fiscal |
| Nota de crédito | 05 | Rebajar un CCF ya sellado | Detalle del CCF → Notas |
| Nota de débito | 06 | Aumentar un CCF ya sellado | Detalle del CCF → Notas |
| Comprobante de retención | 07 | Cuando la agencia es agente de retención designado | Compras (DTE) |
| Factura de sujeto excluido | 14 | Compras a proveedores sin NRC (por ejemplo, un guía independiente) | Compras (DTE) |

### Emitir una factura

1. En **Facturas (DTE)** pulsa **Nueva factura**.
2. Elige **Factura (consumidor final)** o **Crédito fiscal (empresa)**. El crédito fiscal pide razón social, NIT, NRC, actividad, dirección completa y si el cliente es gran contribuyente (retiene el 1 %).
3. Añade las líneas desde el catálogo o como línea libre. **Los precios incluyen IVA.**
4. Pulsa **Emitir factura** y luego, en su detalle, **Generar y enviar DTE**.

En producción, el PDF y el archivo JSON se envían solos al correo del cliente al quedar sellados. Desde el detalle también se descargan o se reenvían a otro correo. En el ambiente de prueba no se envía nada automáticamente.

### Estados del DTE

| Estado | Qué significa | Qué hacer |
| --- | --- | --- |
| Aceptado MH | Sellado por Hacienda; la factura queda emitida | Nada |
| Pendiente de sello | El MH no confirmó todavía | Nada: el sistema reintenta cada 5 minutos |
| En contingencia | Hacienda no respondía; el documento es válido para entregar | Revisar el panel **Contingencias**; el sistema lo transmite después |
| Rechazado MH | Hacienda lo rechazó; el detalle muestra el motivo | Corregir el dato y volver a generar |
| Error | Fallo antes de llegar al MH | Revisar el mensaje y reintentar |
| Invalidado | Anulado ante Hacienda | Nada |

En contingencia hay plazos del MH: 24 horas para el evento y 72 horas para enviar los documentos. El panel **Contingencias** muestra en qué paso está cada una y permite **Procesar ahora**.

### Corregir o anular

- Un **crédito fiscal** sellado se corrige con una nota de crédito o de débito: motivo y líneas con precio **sin IVA** (el sistema suma el 13 %).
- Una **factura 01** no admite notas: se invalida y se emite otra.
- **Invalidar DTE** es irreversible y solo es posible hasta 3 meses después de la transmisión. Hay tres motivos: rescindir la operación (sin reemplazo), error en la información u otro (ambos exigen haber emitido y sellado antes el documento de reemplazo). Un CCF con notas vigentes exige invalidar primero las notas.

### Compras (DTE)

Aparece solo con el DTE activo. **Sujeto excluido** registra compras a proveedores sin NRC: datos del proveedor, líneas de servicio o bien, retención opcional del 10 % de renta y forma de pago. **Comprobante de retención** se habilita si la agencia es agente de retención designado. Cada documento se descarga en PDF y JSON y se envía al proveedor.

Las facturas que nacen de reservas nunca se borran; las manuales solo se editan o eliminan mientras no tengan DTE.

## Finanzas y operaciones

**Finanzas** muestra si cada tour gana dinero: ingresos, gastos y margen. Los ingresos son la suma de las reservas de tour **confirmadas**, no de los cobros recibidos.

| Pestaña | Qué contiene |
| --- | --- |
| Resumen | Ingresos, gastos y margen; gráficos de ingresos frente a gastos por tour y de gastos por categoría; qué tours son rentables |
| Agregar gasto | Tour o gasto general, monto, categoría, fecha (vacía = hoy), guía, comentario y foto del recibo (hasta 10 MB) |
| Ver gastos | Filtros por tour, categoría, guía y fechas; ver recibo, editar o eliminar |
| Ingresos | Ingresos por tour |
| Configuración | Categorías de gasto (nombre e icono) y guías: dar o quitar el rol de guía a un usuario |

Los guías registran sus propios gastos en **Mis gastos**: categoría, monto, fecha, comentario y foto del recibo. Solo ven los suyos.

### Consultas, reseñas y suscriptores

| Sección | Qué se hace | A tener en cuenta |
| --- | --- | --- |
| Consultas | Leer los mensajes del formulario de contacto | Cada consulta llega también por correo. Es de solo lectura: se responde por correo o WhatsApp |
| Reseñas | Aprobar u ocultar, responder públicamente o eliminar | Las reseñas nuevas nacen ocultas hasta aprobarlas. La insignia roja del menú cuenta las pendientes. Solo reseña quien tiene una reserva no cancelada |
| Suscriptores | Buscar, dar de baja o reactivar, eliminar y **Exportar CSV** | Cada suscriptor recibe un correo de bienvenida con su enlace para darse de baja |

## Textos legales

Los términos y condiciones, la política de privacidad y la política de cancelación se editan en **Sistema → Textos legales** y se publican en /terminos, /privacidad y /cancelacion, enlazados desde el pie de la portada. El texto está solo en español: es el que vincula legalmente.

Los tres documentos empiezan como **borrador**, con los datos que faltan marcados en amarillo como `[pendiente: …]`. Hay que completarlos y hacer que un abogado los revise antes de darlos por definitivos.

Para editar un documento:

1. Elige la pestaña: Términos y condiciones, Privacidad o Cancelación y reembolsos.
2. Escribe en el cuadro de la izquierda. El contador amarillo indica cuántos `[pendiente: …]` quedan.
3. Pulsa **Guardar**. La columna de la derecha muestra el texto tal como lo verá el cliente.
4. Usa **Ver en el sitio** para revisarlo en la página pública.

El texto usa un formato sencillo; no admite HTML:

| Escribes | Se ve como |
| --- | --- |
| `## Pagos` | Un título de sección |
| `- Elemento` | Una viñeta |
| `**importante**` | **importante** |
| `[política de privacidad](/privacidad)` | Un enlace |
| Una tabla con `\|` entre columnas | Una tabla, como la de reembolsos |

Los marcadores entre llaves se sustituyen solos por los datos actuales, así que cambiar el correo de contacto no obliga a reescribir las políticas:

| Marcador | Se rellena con |
| --- | --- |
| `{{sitio}}` | Nombre del sitio |
| `{{razon_social}}` · `{{nit}}` | Razón social y NIT de Configuración → Factura Electrónica |
| `{{direccion}}` · `{{correo}}` · `{{telefono}}` | Datos de contacto de Configuración |
| `{{web}}` | Dirección del sitio |
| `{{horas_cancelacion}}` | Ventana de cancelación de Configuración |

**Qué aceptan los clientes.** Al crear una cuenta y al reservar un tour o un vehículo, el cliente debe marcar "Acepto los términos y condiciones, la política de privacidad y la política de cancelación". El sistema guarda la fecha y la versión aceptada, que es la fecha de la última vez que se guardó cualquiera de los tres textos. Por eso conviene guardar solo cambios definitivos: cada guardado crea una versión nueva. Las reservas que el equipo registra desde el panel no piden esta casilla.

## Usuarios, roles y permisos

Cada persona del equipo necesita su propia cuenta con el rol que corresponde a su trabajo. El rol decide qué secciones ve en el menú.

| Rol | Para quién | Qué ve en el panel |
| --- | --- | --- |
| superadmin | El responsable técnico | Todo, sin restricciones |
| admin | Gerencia de la agencia | Todo: también usuarios, roles, configuración, textos legales, cupones, consultas, suscriptores y la gestión completa de reservas |
| finanzas | Contabilidad | Dashboard, Pagos, Facturas, Compras y Finanzas. Emite e invalida DTE y confirma enlaces de pago. No gestiona reservas ni tours |
| editor | Contenido y catálogo | Tours, vehículos, categorías, monedas, mapa, galería y reseñas. No cambia estados de reservas |
| guía | Guías de campo | Solo Mis gastos |
| user | Clientes | No entra al panel |

### Crear una cuenta del equipo

1. En **Administración → Usuarios** crea un usuario nuevo.
2. Escribe nombre, apellido, usuario, correo y una contraseña de al menos 8 caracteres.
3. Asigna el rol y guarda. La persona recibe un correo para verificar su dirección.

Reglas que protegen las cuentas: nadie puede cambiar sus propios roles, solo un superadmin otorga el rol superadmin y nadie puede dar permisos que no tiene. Cambiar el correo de un usuario le obliga a verificarlo de nuevo y cierra sus sesiones abiertas.

### Roles a medida

En **Administración → Roles** se crean roles con los permisos exactos que se necesiten, con un buscador de permisos. Los roles admin y superadmin no se pueden borrar y solo un superadmin los modifica. Un rol con usuarios asignados no se puede eliminar.

## Problemas frecuentes

| Problema | Causa | Solución |
| --- | --- | --- |
| No veo una sección del menú | Tu rol no la incluye, o el rol cambió con la sesión abierta | Cierra sesión y vuelve a entrar; si sigue sin verse, pide el rol a un admin |
| No aparece el botón de WhatsApp del mapa | Falta el WhatsApp de contacto | Configuración → General → Contacto |
| No aparece "Compras (DTE)" | La facturación electrónica está apagada | Configuración → Factura Electrónica |
| No puedo activar la facturación electrónica | Faltan datos del emisor o no son válidos | Corrige los campos que indica el mensaje de error |
| Un cliente no puede cancelar | La reserva ya está confirmada o está dentro de la ventana de cancelación | Cancélala desde el panel si procede |
| La reserva presencial no se guarda | Sin cupo, fecha antes de la antelación mínima o máximo diario alcanzado | Ajusta la fecha o los límites en Configuración → Regional |
| El video de Instagram del pin sale en blanco | El post es privado, se borró o no es un post o reel | Usa un post o reel público; el enlace "Ver en Instagram" siempre funciona |
| Un pago con tarjeta no confirmó la reserva | Wompi cobra, pero la confirmación es manual | Confirma la reserva en Tours agendados |
| No se sube un logo | Formato SVG o archivo de más de 4 MB | Usa PNG, JPG o WEBP de hasta 4 MB |
| Una factura quedó "Pendiente de sello" | Hacienda no respondió a tiempo | Espera: se reintenta sola cada 5 minutos |
| Las fotos de un tour nuevo no se pueden subir | Las imágenes se suben al editar | Guarda el tour, ábrelo y pulsa Gestionar imágenes |

### Limitaciones conocidas

Estas funciones todavía no se comportan como deberían. Están anotadas para corregirlas:

- Las cifras principales del **Dashboard** aparecen en 0. Consulta los datos en Finanzas, Pagos y Tours agendados.
- El estado **Completada** da error al guardar y las **notas internas** de "Editar estado" no se guardan.
- No hay botón para marcar como **cobrado un pago en efectivo** pendiente.
- **Generar factura** desde una reserva produce un documento imprimible sin valor fiscal y con datos de contacto de ejemplo. Para facturar, usa Facturas (DTE).
- El formulario **Nueva reserva del Calendario** deja la reserva a nombre de quien la crea; para clientes usa Nueva reserva (presencial).
- Algunas insignias de estado de reservas y pagos se muestran en inglés (pending, paid) y el correo de confirmación de reserva tiene el asunto en inglés.

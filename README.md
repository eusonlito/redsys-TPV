Redsys TPV
=====

Este script te permitirá generar los formularios para la integración de la pasarela de pago de Redsys (antes Sermepa / Servired).

## Instalación

Añade las dependencias vía composer: `"redsys/tpv": "1.*"`

```bash
composer update
```

## Ejemplo de pago instantáneo

Este proceso se realiza para pagos en el momento, sin necesidad de confirmación futura (TransactionType = 0)

```php
# Incluye tu arquivo de configuración (copia config.php para config.local.php)

$config = require (__DIR__.'/config.local.php');

# Cargamos la clase con los parámetros base

$TPV = new Redsys\Tpv\Tpv($config);

# Indicamos los campos para el pedido

$TPV->setFormHiddens(array(
    'TransactionType' => '0',
    'MerchantData' => 'Televisor de 50 pulgadas',
    'Order' => '012121323',
    'Amount' => '568,25',
    'UrlOK' => 'http://dominio.com/direccion-todo-correcto/',
    'UrlKO' => 'http://dominio.com/direccion-error',
    'MerchantURL' => 'http://dominio.com/direccion-control-pago'
));

# Imprimimos el pedido el formulario y redirigimos a la TPV

echo '<form action="'.$TPV->getPath('/realizarPago').'" method="post">'.$TPV->getFormHiddens().'</form>';

die('<script>document.forms[0].submit();</script>');
```

Para realizar el control de los pagos, la TPV se comunicará con nosotros a través de la url indicada en **MerchantURL**.

Este script no será visible ni debe responder nada, simplemente verifica el pago.

El banco siempre se comunicará con nosotros a través de esta url, sea correcto o incorrecto.

Podemos realizar un script (Lo que en el ejemplo sería http://dominio.com/direccion-control-pago) que valide los pagos de la siguiente manera:

```php
# Incluye tu arquivo de configuración (copia config.php para config.local.php)

$config = require (__DIR__.'/config.local.php');

# Cargamos la clase con los parámetros base

$TPV = new Redsys\Tpv\Tpv($config);

# Realizamos la comprobación de la transacción

try {
    $TPV->checkTransaction($_POST);
} catch (\Exception $e) {
    file_put_contents(__DIR__.'/logs/errores-tpv.log', $e->getMessage(), FILE_APPEND);
    die();
}

# Actualización del registro en caso de pago (ejemplo usando mi framework)

$Db->update(array(
    'table' => 'tpv',
    'limit' => 1,
    'data' => array(
        'operacion' => $_POST['Ds_TransactionType'],
        'fecha_pago' => date('Y-m-d H:i:s')
    ),
    'conditions' => array(
        'id' => $_POST['Ds_Order']
    )
));

die();
```

## Ejemplo de pago en diferido

Este proceso se realiza para pagos mediante autorización inicial y posterior confirmación del pago sin que el cliente se encuentre presente (TransactionType = 1)

El proceso es exactamente igual que el anterior, sólamente se debe cambiar el valor de inicalización de `TransactionType` de `0` a `1`.

Una vez completado todo el proceso anterior, debemos crear dos scripts en nuestro proyecto, uno para iniciar la confirmación del pago y otro para verificar el proceso.

```php
# Incluye tu arquivo de configuración (copia config.php para config.local.php)

$config = require (__DIR__.'/config.local.php');

# Cargamos la clase con los parámetros base

$TPV = new Redsys\Tpv\Tpv($config);

# Indicamos los campos para la confirmación del pago

$TPV->sendXml(array(
    'TransactionType' => '2', // Código para la Confirmación del cargo
    'MerchantURL' => 'http://dominio.com/direccion-control-pago-xml', // A esta URL enviará el banco la confirmación del cobro
    'Amount' => '568,25', // La cantidad final a cobrar
    'Order' => '012121323', // El número de pedido, que debe existir en el sistema bancario a través de una autorización previa
    'MerchantData' => 'Televisor de 50 pulgadas',
));
```

Esta ejecución nos devolverá un XML con una respuesta sobre este envío, pero la respuesta sobre el resultado de la operación serán enviada desde el banco a la URL indicada en MerchantURL.

Para verificar que el envío se ha realizado correctamente, el banco devuelve un XML con un valor para la etiqueta de  de `CODIGO` que devemos verificar para saber si el envío ha sido correcto.

Ahora vamos a por el script de `http://dominio.com/direccion-control-pago-xml` en que recogemos el resultado del pago:

```php
# Incluye tu arquivo de configuración (copia config.php para config.local.php)

$config = require (__DIR__.'/config.local.php');

# Cargamos la clase con los parámetros base

$TPV = new Redsys\Tpv\Tpv($config);

# Obtenemos los datos remitidos por el banco en formato `array`

$datos = $TPV->xmlString2array($_POST['datos']);

# Realizamos la comprobación de la transacción

try {
    $TPV->checkTransaction($datos);
} catch (\Exception $e) {
    file_put_contents(__DIR__.'/logs/errores-tpv.log', $e->getMessage(), FILE_APPEND);
    die();
}

# Actualización del registro en caso de pago (ejemplo usando mi framework)

$Db->update(array(
    'table' => 'tpv',
    'limit' => 1,
    'data' => array(
        'pagado' => 1,
        'operacion' => $datos['Ds_TransactionType'],
        'fecha_pago' => date('Y-m-d H:i:s')
    ),
    'conditions' => array(
        'id' => $datos['Ds_Order']
    )
));

die();
```

--------

# Integración con Redsys/Fake

Si deseas probar la conexión con un servidor propio de Redsys, puedes instalar el siguiente servicio en tu servidor https://github.com/eusonlito/redsys-Fake

La configuración para conexión a este servicio sería la siguiente

```php
$TPV = new Redsys\Tpv\Tpv(array(
    'environments' => array(
        'local' => 'http://redsys-fake.mydomain.com'
    ),

    'Environment' => 'local',
    'Key' => 'asdfghjkd0123456789', // Debe coincidir con el valor de Key del entorno de pruebas

    ....
));
```

Si deseas más información sobre parámetros u opciones, Google puede echarte una mano https://www.google.es/search?q=manual+instalaci%C3%B3n+redsys+php+filetype%3Apdf

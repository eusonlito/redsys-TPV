Redsys
=====

Este script te permitirá generar los formularios para la integración de la pasarela de pago de Redsys (antes Sermepa / Servired).

Ejemplo de envío a pasarela:

```php
include (__DIR__.'/libs/ANS/Redsys/Redsys.php');

# Cargamos la clase con los parámetros base

$Redsys = new \ANS\Redsys\Redsys(array(
    'Environment' => 'test', // Puedes indicar test o real
    'MerchantCode' => '1234567890',
    'Key' => 'asdfghjkd0123456789',
    'Terminal' => '1',
    'TransactionType' => '0',
    'Currency' => '978',
    'MerchantName' => 'COMERCIO',
    'Titular' => 'Mi Comercio',
    'Currency' => '978',
    'Terminal' => '1',
    'ConsumerLanguage' => '001'
));

# Indicamos los campos para el pedido

$Redsys->setFormHiddens(array(
    'MerchantData' => 'Televisor de 50 pulgadas',
    'Order' => '012121323',
    'Amount' => '568,25',
    'UrlOK' => 'http://dominio.com/direccion-todo-correcto/',
    'UrlKO' => 'http://dominio.com/direccion-error',
    'MerchantURL' => 'http://dominio.com/direccion-control-pago'
));

# Imprimimos el pedido el formulario y redirigimos a la TPV

echo '<form action="'.$TPV->getEnvironment().'" method="post">'.$TPV->getFormHiddens().'</form>';

die('<script>document.forms[0].submit();</script>');
```

Para realizar el control de los pagos, la TPV se comunicará con nosotros a través de la url indicada en **MerchantURL**.

Este script no será visible ni debe responder nada, simplemente verifica el pago.

El banco siempre se comunicará con nosotros a través de esta url, sea correcto o incorrecto.

Podemos realizar un script (Lo que en el ejemplo sería http://dominio.com/direccion-control-pago) que valide los pagos de la siguiente manera:

```php
include (__DIR__.'/libs/ANS/Redsys/Redsys.php');

# Cargamos la clase con los parámetros base

$Redsys = new \ANS\Redsys\Redsys(array(
    'Environment' => 'test', // Puedes indicar test o real
    'MerchantCode' => '1234567890',
    'Key' => 'asdfghjkd0123456789',
    'Terminal' => '1',
    'TransactionType' => '0',
    'Currency' => '978',
    'MerchantName' => 'COMERCIO',
    'Titular' => 'Mi Comercio',
    'Currency' => '978',
    'Terminal' => '1',
    'ConsumerLanguage' => '001'
));

# Realizamos la comprobación de la transacción

try {
    $Redsys->checkTransaction($_POST);
} catch (\Exception $e) {
    file_put_contents(__DIR__.'/logs/errores-tpv.log', $e->getMessage(), FILE_APPEND);
    die();
}

# Actualización del registro en caso de pago (ejemplo usando mi framework)

$Db->update(array(
    'table' => 'tpv',
    'limit' => 1,
    'data' => array(
        'fecha_pago' => date('Y-m-d H:i:s')
    ),
    'conditions' => array(
        'id' => $_POST['Ds_Order']
    )
));

die();
```

Simplemente con esto ya tenemos el proceso completado.

--------

Una manera más elegante sería guardando la configuración en un fichero llamado por ejemplo `config.php` e incluirlo directamente en la carga de la clase:

```php
return array(
    'Environment' => 'test', // Puedes indicar test o real
    'MerchantCode' => '1234567890',
    'Key' => 'asdfghjkd0123456789',
    'Terminal' => '1',
    'TransactionType' => '0',
    'Currency' => '978',
    'MerchantName' => 'COMERCIO',
    'Titular' => 'Mi Comercio',
    'Currency' => '978',
    'Terminal' => '1',
    'ConsumerLanguage' => '001'
);
```

y así incluimos directamente el fichero y evitamos ensuciar el script con líneas de configuración

```php
include (__DIR__.'/libs/ANS/Redsys/Redsys.php');

$Redsys = new \ANS\Redsys\Redsys(require(__DIR__.'/config.php'));
```

Para gustos, colores :)

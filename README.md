# License
CC BY-NC 4.0 (Attribution-NonCommercial 4.0 International)

# ecoflow-api-php
Simple class for the Ecoflow API. Please note: highly experimental and probably not fully functional.

```php
    // Example of how to use the API

    $ecoflow = new EcoFlowAPI( 'yourAccessKey', 'yourSecretKey' );

    // List all devices tied to the account
    print_r( $ecoflow->getDevices() );

    // Show the base load of a powerstream inverter
    $inv = $ecoflow->getDevice('yourPsSerial');
    echo "Base load: " . $inv['data']['20_1.permanentWatts'];

    // Set a new base load of a powerstream inverter;
    // see all commands @ https://developer-eu.ecoflow.com/us/document/powerStreamMicroInverter
    $ecoflow->setDeviceFunction('yourPsSerial',         // serial
        'WN511_SET_PERMANENT_WATTS_PACK',               // command
        [ 'permanent_watts' => 2000 ]                   // data (here: 200 Watts; PS uses 0.1 units)
    );

```

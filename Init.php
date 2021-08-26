<?php
namespace FacturaScripts\Plugins\AsientosPredefinidos;

class Init extends \FacturaScripts\Core\Base\InitClass
{
    public function init() {
        /// se ejecutara cada vez que carga FacturaScripts (si este plugin está activado).
        $this->loadExtension(new Extension\Controller\ListAsiento());
    }

    public function update() {
        /// se ejecutara cada vez que se instala o actualiza el plugin.
    }
}
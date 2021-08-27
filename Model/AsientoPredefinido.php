<?php
namespace FacturaScripts\Plugins\AsientosPredefinidos\Model;

class AsientoPredefinido extends \FacturaScripts\Core\Model\Base\ModelClass
{
    use \FacturaScripts\Core\Model\Base\ModelTrait;

    public $idasientopredefinido;
    public $descripcion;
    public $debaja;
    public $fechabaja;
    

    public function clear() {
        parent::clear();
    }

    public static function primaryColumn() {
        return "idasientopredefinido";
    }

    public static function tableName() {
        return "asientospredefinidos";
    }
    
    public function test() {

        $this->debaja = !empty($this->fechabaja);
        
        $this->evitarInyeccionSQL();
        return parent::test();
    }
	
    private function evitarInyeccionSQL()
    {
        $utils = $this->toolBox()->utils();
        $this->descripcion = $utils->noHtml($this->descripcion);
    }
    
}

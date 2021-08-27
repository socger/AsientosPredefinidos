<?php
namespace FacturaScripts\Plugins\AsientosPredefinidos\Model;

class AsientoPredefinidoLinea extends \FacturaScripts\Core\Model\Base\ModelClass
{
    use \FacturaScripts\Core\Model\Base\ModelTrait;

    /**
     * 
     * @var string 
     */
    public $codsubcuenta;
    
    /**
     * 
     * @var string 
     */
    public $codcontrapartida;

    /**
     * 
     * @var string 
     */
    public $concepto;

    /**
     * 
     * @var float
     */
    public $debe;
    
    /**
     * 
     * @var float
     */
    public $haber;

    /**
     * 
     * @var int
     */
    public $id;

    /**
     * 
     * @var int
     */
    public $idasientopre;

    /**
     * 
     * @var int
     */
    public $orden;

    public function clear() {
        parent::clear();
        
        $this->debe = 0;
        $this->haber = 0;
        $this->orden = 0;
    }

    public static function primaryColumn() {
        return "id";
    }

    public static function tableName() {
        return "asientospre_lineas";
    }
    
    public function test() {
        $this->concepto = $this->toolBox()->utils()->noHtml($this->concepto);
        return parent::test();
    }
	
    
}

<?php

namespace app\grabbers;

use app\models\Currency;
use app\models\ExchangeRateGrabberInfo;

use app\grabbers\CommonBankGrabStrategy;

/**
 * This is abstract class for grabbing banks exchanges
 */
abstract class ExchangeRateGrabberStrategyAbstract
{

    /** @var array Array to store grabbed exchange values */
    protected $exchanges = [];

    /** @var ExchangeRateGrabberInfo Metadata of grabber */
    protected $info;

    /** @var array Array to store grabber currency checker values */
    private static $currency_checker;

    /**
     * Create strategry from strategy name
     * It can return existing class object or construct CommonBankGrabStrategy with bankname
     * 
     * @param string $bankname
     * @return ExchangeRateGrabbingStrategyInterface
     */
    public static function create($bankname)
    {
        $classname = __NAMESPACE__ . '\\banks\\' . $bankname;

        if (class_exists($classname)) {
            return new $classname;
        }

        return new CommonBankGrabStrategy($bankname);
    }

    /**
     * Constructor is getting database grabber info from class name
     * 
     * @throws \Exception Throws \Exception if echange rate grabber info not found
     */
    public function __construct()
    {
        $classname = $this->getBankName();

        $info = ExchangeRateGrabberInfo::find()->where(['name' => $classname])->one();

        if (empty($info)) {
            throw new \UnexpectedValueException("broken class: metadata for $classname not found");
        }

        $this->info = $info;
    }

    /**
     * Site URL generation
     * Method(not variable or constant) is used because some sites are require extra data like dates
     * 
     * @return string URL of site to grab
     */
    protected function getUrl()
    {
        if (!empty($this->info->url)) {
            return $this->info->url;
        }
    }

    /**
     * Get Bank ID from strategy metadata
     * 
     * @return int
     */
    public function getBankId()
    {
        if (!empty($this->info->bank_id)) {
            return $this->info->bank_id;
        }
    }
    
    /**
     * Getting bank grabbing strategy name from classname
     * 
     * @return string
     */
    public function getBankName()
    {
        $classname = get_class($this);
        $classname = substr($classname, strrpos($classname, '\\') + 1);
        
        return $classname;
    }
   
    /**
     * Method to save any currency values to exchange array
     * 
     * @param int $currency_id
     * @param float $buy
     * @param float $sale
     * @param string $check
     */
    final protected function saveCurrencyValues($currency_id, $buy, $sale, $check)
    {
        $this->exchanges[$currency_id] = [
            'buy' => $buy,
            'sale' => $sale,
            'check' => $check
        ];        
    }
    
    /**
     * This method is to validate and return exchange rates
     * 
     * @return array Exchange array
     * @throws \Exception
     */
    final protected function returnValues()
    {
        // check if values exists

        if (empty($this->exchanges)) {
            throw new \RuntimeException('broken markup:no exchange');
        }

        // build currency checker values

        if (empty(self::$currency_checker)) {

            $currency_checker = [];
            $currencies = Currency::find()->all();

            foreach ($currencies as $currency) {
                if (empty($currency_checker[$currency->id])) {
                    $currency_checker[$currency->id] = [];
                }

                // push currency code and symbol
                array_push($currency_checker[$currency->id], $currency->code, $currency->symbol);

                // add additional grabber currency checker values
                if (!empty($currency->grabberCurrencyCheckers)) {
                    foreach ($currency->grabberCurrencyCheckers as $checker) {
                        array_push($currency_checker[$currency->id], $checker->value);
                    }
                }
            }

            self::$currency_checker = $currency_checker;

        }

        // check for values of exchanges and currency checker

        foreach ($this->exchanges as $currency => $exchange) {

            if ($currency * $exchange['buy'] * $exchange['sale'] == 0) {
                throw new \RuntimeException('broken markup:no exchange');
            }

            if (!in_array($exchange['check'], self::$currency_checker[$currency])) {
                throw new \RuntimeException('broken markup:check fail');
            }
        }

        return $this->exchanges;
    }

}
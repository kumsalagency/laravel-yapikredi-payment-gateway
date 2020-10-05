<?php


namespace KumsalAgency\Payment\YapiKredi\Response;


use KumsalAgency\Payment\PaymentResponse;

class ThreeDPaymentPrepareResponse extends PaymentResponse
{
    /**
     * ThreeDPaymentResponse constructor.
     * @param $response
     */
    public function __construct($response)
    {
        parent::__construct($response);

        $this->xml();
    }

    /**
     * Determine if the response was successful.
     *
     * @return bool
     */
    public function successful()
    {
        return isset($this->decoded['approved']) ? ($this->decoded['approved'] == '1') : false;
    }

    /**
     * Get message
     *
     * @return string
     */
    public function getMessage()
    {
        return isset($this->decoded['respCode']) &&
                is_string($this->decoded['respCode']) &&
                trans()->has('payment::payment.yapikredi.messages.'.($this->decoded['respCode'] ?? '0')) ?
                    trans('payment::payment.yapikredi.messages.'.($this->decoded['respCode'] ?? '0')) :
                    (isset($this->decoded['respText']) &&
                    is_string($this->decoded['respText']) ?
                        $this->decoded['respText'] :
                        ($this->successful() ?
                            '' :
                            trans('payment::payment.error.0')
                        )
                    );
    }

    /**
     * Get code
     *
     * @return string
     */
    public function getCode()
    {
        return $this->successful() ?
            '' :
            (isset($this->decoded['respCode']) &&
            is_string($this->decoded['respCode']) ?
                $this->decoded['respCode']
                : 0
            );
    }

    /**
     * Get ID
     *
     * @return string
     */
     public function getID()
     {
         return '';
     }

    /**
     * Get ID
     *
     * @return string
     */
     public function getSign()
     {
         return $this->decoded['oosRequestDataResponse']['sign'] ?? '';
     }

    /**
     * Get ID
     *
     * @return string
     */
     public function getData1()
     {
         return $this->decoded['oosRequestDataResponse']['data1'] ?? '';
     }

    /**
     * Get ID
     *
     * @return string
     */
     public function getData2()
     {
         return $this->decoded['oosRequestDataResponse']['data2'] ?? '';
     }
}
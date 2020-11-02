<?php


namespace KumsalAgency\Payment\YapiKredi;


use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use KumsalAgency\Payment\PaymentException;
use KumsalAgency\Payment\PaymentGateway;
use KumsalAgency\Payment\PaymentResponse;
use KumsalAgency\Payment\YapiKredi\Response\ThreeDPaymentPrepareResponse;
use KumsalAgency\Payment\YapiKredi\Response\ThreeDPaymentResolveResponse;
use KumsalAgency\Payment\YapiKredi\Response\ThreeDPaymentResponse;
use Spatie\ArrayToXml\ArrayToXml;

class YapiKredi extends PaymentGateway
{
    /**
     * @var array
     */
    public array $currenciesNormalization = [
        'TRY'   => 'TL',
        'USD'   => 'US',
        'EUR'   => 'EU',
        'GBP'   => 'GB',
        'JPY'   => 'JP',
        'RUB'   => 'RU',
    ];

    /**
     * YapiKredi constructor.
     * @param Application $application
     * @param array $config
     */
    public function __construct(Application $application, array $config)
    {
        parent::__construct($application, $config);
    }

    public function payment()
    {
        if ($this->isThreeD)
        {
            try {

                $response = Http::baseUrl($this->config['base_url'])
                    ->asForm()
                    ->post('PosnetWebService/XML',[
                    'xmldata' => ArrayToXml::convert([
                        'mid'   => $this->config['client_id'] ?? '',
                        'tid'   => $this->config['terminal_id'] ?? '',
                        'oosRequestData' => [
                            'posnetid'          => $this->config['posnet_id'] ?? '',
                            'ccno'              => $this->cardNumber,
                            'expDate'           => Str::substr((string) $this->cardExpireDateYear,-2).Str::padLeft((string) $this->cardExpireDateMonth,2,'0'),
                            'cvc'               => $this->cardCVV2,
                            'amount'            => $this->amount * 100,
                            'currencyCode'      => $this->currenciesNormalization[$this->currency] ?? $this->currency,
                            'installment'       => $this->installmentCount ?? ($this->installmentCount > 1 ? $this->installmentCount : 0),
                            'XID'               => str_pad($this->orderID, 20, '0', STR_PAD_LEFT),
                            'cardHolderName'    => $this->cardHolderName,
                            'tranType'          => 'Sale',
                        ]
                    ], [
                        'rootElementName' => 'posnetRequest',
                    ], true, 'UTF-8')
                ]);

                if (!$response->successful())
                {
                    throw new PaymentException(null,PaymentException::ErrorConnection);
                }

                $threeDPaymentPrepareResponse  = new ThreeDPaymentPrepareResponse($response->body());

                if (!$threeDPaymentPrepareResponse->successful())
                {
                    throw new PaymentException(null,PaymentException::ErrorConnection);
                }

                return response()->view('laravel-yapikredi-payment-gateway::redirect',[
                    'formData' => [
                        'gateway'       => $this->config['base_url'].'/3DSWebService/YKBPaymentService',
                        'success_url'   => $this->successUrl,
                        'fail_url'      => $this->failUrl,
                        'rand'          => $threeDPaymentPrepareResponse->getSign(),
                        'hash'          => $threeDPaymentPrepareResponse->getData1(),
                        'inputs'        => [
                            'posnetData'         => $threeDPaymentPrepareResponse->getData1(),
                            'posnetData2'        => $threeDPaymentPrepareResponse->getData2(),
                            'mid'                => $this->config['client_id'],
                            'posnetID'           => $this->config['posnet_id'],
                            'digest'             => $threeDPaymentPrepareResponse->getSign(),
                            'vftCode'            => $this->config['promotion_code'] ?? null,
                            'merchantReturnURL'  => $this->successUrl,
                            'url'                => '',
                            'lang'               => app()->getLocale(),
                        ],
                    ]
                ]);
            }
            catch(ConnectionException $exception)
            {
                Log::error($exception);

                throw new PaymentException(null,PaymentException::ErrorConnection,$exception);
            }
            catch (\Exception $exception)
            {
                Log::error($exception);

                throw new PaymentException(null,PaymentException::ErrorGeneral,$exception);
            }
        }
        else
        {
            throw new PaymentException(null,PaymentException::ErrorNotSupportedMailOrder);
        }
    }

    /**
     * @param Request $request
     * @return PaymentResponse
     * @throws PaymentException
     */
    public function paymentThreeDFallback(Request $request): PaymentResponse
    {
        try {
            if (!$this->checkThreeDHash($request))
            {
                throw new PaymentException(null,PaymentException::ErrorPosUnexpectedReturn);
            }

            $resolveResponse = Http::baseUrl($this->config['base_url'])
                ->withBody('xmldata='.
                    ArrayToXml::convert([
                        'mid'   => $this->config['client_id'] ?? '',
                        'tid'   => $this->config['terminal_id'] ?? '',
                        'oosResolveMerchantData' => [
                            'bankData'      => $request->get('BankPacket'),
                            'merchantData'  => $request->get('MerchantPacket'),
                            'sign'          => $request->get('Sign'),
                            'mac'           => $this->getMac(),
                        ]
                    ], [
                        'rootElementName' => 'posnetRequest',
                    ], true, 'ISO-8859-9'),
                    'application/x-www-form-urlencoded; charset=utf-8')
                ->post('PosnetWebService/XML');

            if (!$resolveResponse->successful())
            {
                throw new PaymentException(null,PaymentException::ErrorConnection);
            }

            $threeDPaymentResolveResponse = new ThreeDPaymentResolveResponse($resolveResponse->body());

            if (!$threeDPaymentResolveResponse->successful())
            {
                throw new PaymentException($threeDPaymentResolveResponse->getMessage(),$threeDPaymentResolveResponse->getCode());
            }

            $response = Http::baseUrl($this->config['base_url'])
                ->withBody('xmldata='.
                    ArrayToXml::convert([
                        'mid'   => $this->config['client_id'] ?? '',
                        'tid'   => $this->config['terminal_id'] ?? '',
                        'oosTranData' => [
                            'bankData'      => $request->get('BankPacket'),
                            'merchantData'  => $request->get('MerchantPacket'),
                            'sign'          => $request->get('Sign'),
                            'wpAmount'      => $threeDPaymentResolveResponse->xml()['oosResolveMerchantDataResponse']['amount'],
                            'mac'           => $this->getMac(),
                        ]
                    ], [
                        'rootElementName' => 'posnetRequest',
                    ], true, 'ISO-8859-9'),
                    'application/x-www-form-urlencoded; charset=utf-8')
                ->post('PosnetWebService/XML');

            if (!$response->successful())
            {
                throw new PaymentException(null,PaymentException::ErrorConnection);
            }

            return new ThreeDPaymentResponse($response->body());

        }
        catch (ConnectionException $exception)
        {
            Log::error($exception);

            throw new PaymentException(null,PaymentException::ErrorConnection);
        }
        catch (PaymentException $exception)
        {
            throw new PaymentException($exception->getMessage(),$exception->getCode());
        }
        catch (\Exception $exception)
        {
            Log::error($exception);

            throw new PaymentException(null,PaymentException::ErrorGeneral);
        }
    }

    /**
     * @param Request $request
     * @return bool
     */
    protected function checkThreeDHash(Request $request): bool
    {
        $yapiKrediCrypt = new YapiKrediCrypt();

        $deCryptedDataArray = explode(';',$yapiKrediCrypt->decrypt($request->get('MerchantPacket'), $this->config['store_key'] ?? null));
        $yapiKrediCrypt->deInit();

        return array_map('strval', [
            $this->config['client_id'] ?? null,
            $this->config['terminal_id'] ?? null,
            $this->amount * 100,
            $this->installmentCount > 1 ? $this->installmentCount : "00",
            str_pad($this->orderID, 20, '0', STR_PAD_LEFT),
        ]) == array_map('strval', [
                $deCryptedDataArray[0],
                $deCryptedDataArray[1],
                $deCryptedDataArray[2],
                $deCryptedDataArray[3],
                $deCryptedDataArray[4],
            ]);
    }

    /**
     * @param $data
     * @return string
     */
    protected function getHash($data): string
    {
        return base64_encode(hash('sha256',$data,true));
    }

    /**
     * @return string
     */
    protected function getMac(): string
    {
        return urlencode(
            $this->getHash(
                str_pad($this->orderID, 20, '0', STR_PAD_LEFT).";" .
                ($this->amount * 100).";".
                ($this->currenciesNormalization[$this->currency] ?? $this->currency).";".
                $this->config['client_id'].";".
                $this->getHash(
                    $this->config['store_key'].";".
                    $this->config['terminal_id']
                )
            )
        );
    }
}
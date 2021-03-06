<?php

namespace Shopsys\FrameworkBundle\Model\Payment;

class PaymentDomainFactory implements PaymentDomainFactoryInterface
{

    /**
     * @param \Shopsys\FrameworkBundle\Model\Payment\Payment $payment
     * @param int $domainId
     * @return \Shopsys\FrameworkBundle\Model\Payment\PaymentDomain
     */
    public function create(Payment $payment, int $domainId): PaymentDomain
    {
        return new PaymentDomain($payment, $domainId);
    }
}

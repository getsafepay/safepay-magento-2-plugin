<?php

namespace Safepay\Checkout\Model;

use Magento\Framework\UrlInterface;

class SystemConfigProductionComment implements \Magento\Config\Model\Config\CommentInterface
{
    protected $urlInterface;

    public function __construct(
        UrlInterface $urlInterface
    ) {
        $this->urlInterface = $urlInterface;
    }

    public function getCommentText($elementValue)
    {
        $url = $this->urlInterface->getBaseUrl();

        $base_url = str_replace("http://", "https://", $url);

        return 'Using webhook secret keys allows Safepay to verify each payment. To get your live webhook key:  <br/>&nbsp;&nbsp; 1. Navigate to your Live Safepay dashboard by clicking <a  target="__blank" href="https://getsafepay.com/dashboard/api-settings">here</a> <br/>&nbsp;&nbsp; 2. Click \'Add an endpoint\' and paste the following URL:<a href=\'#\'> '.$base_url .'safepay/webhook/receiver</a> <br/>&nbsp;&nbsp; 3. Make sure to select "Send me all events", to receive all payment updates. <br/>&nbsp;&nbsp; 4. Click "Show shared secret" and paste into the box above.';
    }
}

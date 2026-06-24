<?php
/**
<<<<<<<< HEAD:src/system/mastercardpaymentexception.php
 * Copyright (c) 2019-2026 Mastercard
========
 * Copyright (c) 2026 Mastercard
>>>>>>>> a570838d489fb2e7a62dabbb5dac47bdb7b1ab51:src/upload/admin/controller/extension/payment/mpgs_payment_exception.php
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
<<<<<<<< HEAD:src/system/mastercardpaymentexception.php
 *
 * @package  Mastercard
 * @version  GIT: @1.3.4@
 * @link     https://github.com/fingent-corp/gateway-opencart-mastercard-module
 */

namespace Opencart\Admin\Controller\Extension\MasterCard\Payment;

use Opencart\Admin\Model\Extension\MasterCard\Payment;

class MasterCardPaymentException extends \Opencart\System\Engine\Controller {

}

========
 */

namespace Mpgs\Payment;

/**
 * Dedicated exception for MPGS Hosted Checkout payment errors.
 *
 * Thrown instead of the generic \Exception so that SonarQube rule S112
 * (no generic exception) is satisfied and callers can distinguish
 * MPGS-specific payment failures from other runtime errors.
 */
class MpgsPaymentException extends \RuntimeException {}
>>>>>>>> a570838d489fb2e7a62dabbb5dac47bdb7b1ab51:src/upload/admin/controller/extension/payment/mpgs_payment_exception.php

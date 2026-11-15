<?php

namespace App\Http\Requests\Customers;

/**
 * Same rules as creation, with every field optional and the phone unique except for this customer.
 */
class UpdateCustomerRequest extends StoreCustomerRequest {}

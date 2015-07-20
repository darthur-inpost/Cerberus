<?php
// Invoicing class

class ipinvoiceClass
{
    private $size               = array();
    private $weightRangeLower   = array();
    private $weightRangeHigher  = array();
    private $weightPrice        = array();
    private $retentionLower     = array();
    private $retentionHigher    = array();
    private $retentionPrice     = array();
    private $retentionSurcharge = 0;
    private $retentionHours     = 0;
    private $retentionMaxHours  = 0;
    private $retentionMaxHoursPrice = 0;
    private $rpHours            = 0;
    private $rpStandard         = 0;
    private $rpSurcharge        = 0;
    private $calcType           = 0; // Default to SIZE
    private $priceTypes         = array(
                                    "SIZE" => 0,
                                    "WEIGHT" => 1,
                                    "PERIOD" => 2,
                                    "SIMPLE" => 3,
                                );
    private $error              = null;

    ///
    // __construct
    //
    // @param string The type of price to use for eacg invoice line
    // @param class  The error reporting class
    //
    public function __construct($priceType, $error)
    {
        $priceType = strtoupper($priceType);

        if (isset($this->priceTypes[$priceType]))
        {
            // The price type is a valid one.
            $this->calcType = $this->priceTypes[$priceType];
        }
        else
        {
            throw new InvalidArgumentException(
                "__construct needs price type of SIZE, WEIGHT or PERIOD");
        }

        if ($error != null)
        {
            $this->error = $error;
        }

        return true;
    }

    ///
    //
    //
    public function getCalculationType()
    {
        switch($this->calcType)
        {
            case 0:
                $return = "SIZE";
                break;
            case 1:
                $return = "WEIGHT";
                break;
            case 2:
                $return = "PERIOD";
                break;
            case 3:
                $return = "SIMPLE";
                break;
        }

        return $return;
    }

    ///
    // setSizeDetails
    //
    // @brief Setup the three size prices, A, B & C.
    // @param mixed the three size fees
    //
    public function setSizeDetails($details)
    {
        // Check that the three sizes are being set.
        if (!is_array($details) || count($details) != 3)
        {
            $this->error->message("ERROR", "You must specify all three sizes prices");
            throw new InvalidArgumentException("setSizeDetails requires three values for prices, A, B and C");
        }

        foreach ($details as $key => $detail)
        {
            if (!is_float($detail))
            {
                throw new InvalidArgumentException(
                    "setSizeDetails expects to get floating point for arg " .
                    $key);
            }
        }

        foreach($details as $detail)
        {
            $this->size[] = $detail;
        }

        return true;
    }

    ///
    // setWeightDetails
    //
    // @param mixed two column array of data
    //
    public function setWeightDetails($weights)
    {
        // Check that the weight ranges are setup starting with zero and
        // going up in steps
        if (!is_array($weights))
        {
                throw new InvalidArgumentException(
                    "setWeightDetails expects to get an array");
        }
        // Check to see if we have been given an array or arrays containing
        // the weight details
        if (is_array($weights[0]))
        {
            foreach($weights as $weight)
            {
                if (count($weight) != 3)
                {
                    throw new InvalidArgumentException(
                        "setWeightDetails expects to get start, end & price, count of parameters is wrong.");
                }
            }
        }
        elseif (count($weights) % 3 != 0)
        {
                throw new InvalidArgumentException(
                    "setWeightDetails expects to get start, end & price, count of parameters is wrong.");
        }

        // Now check to see if the parameters are correctly typed.
        foreach ($weights as $key => $weight)
        {
            if (is_array($weight))
            {
                foreach($weight as $row)
                {
                    if (!is_numeric($row))
                    {
                        throw new InvalidArgumentException(
                            "setWeightsDetails arg $key is not numeric");
                    }
                }
            }
            elseif (!is_numeric($weight))
            {
                throw new InvalidArgumentException(
                    "setWeightsDetails arg $key is not numeric");
            }
        }

        // Now save the data to the class variables
        $count = 0;
        foreach ($weights as $weight)
        {
            if (is_array($weight))
            {
                // TODO finish this.
                foreach ($weight as $row)
                {
                    switch ($count)
                    {
                        case 0:
                            $this->weightRangeLower[]  = $row;
                            break;
                        case 1:
                            $this->weightRangeHigher[] = $row;
                            break;
                        case 2:
                            $this->weightPrice[]       = $row;
                            break;
                    }
                    $count++;

                    // Check if we have to go back to zero again.
                    if ($count > 2)
                    {
                        $count = 0;
                    }
                }
            }
            else
            {
                switch ($count)
                {
                    case 0:
                        $this->weightRangeLower[]  = $weight;
                        break;
                    case 1:
                        $this->weightRangeHigher[] = $weight;
                        break;
                    case 2:
                        $this->weightPrice[]       = $weight;
                        break;
                }
                $count++;

                // Check if we have to go back to zero again.
                if ($count > 2)
                {
                    $count = 0;
                }
            }
        }

        // Now check that the values entered are consistent
        for ($i = count($this->weightRangeHigher) - 1; $i >= 0; $i--)
        {
            if ($this->weightRangeHigher[$i] <= $this->weightRangeLower[$i])
            {
                $this->error->message("error",
                    "weight range is not setup correctly.");
                throw new InvalidArgumentException(
                    "weight range is not setup correctly");
            }

            if ($i > 0)
            {
                if ($this->weightRangeHigher[$i] <= $this->weightRangeHigher[$i - 1] ||
                    $this->weightRangeHigher[$i] <= $this->weightRangeLower[$i - 1])
                {
                    $this->error->message("error",
                        "weight range setup is not correct.");
                    throw new InvalidArgumentException(
                        "weight range setup is not correct");
                }

                if ($this->weightRangeLower[$i] < $this->weightRangeHigher[$i - 1])
                {
                    $this->error->message("error",
                        "The lower weight is setup incorrectly.");
                    throw new InvalidArgumentException(
                        "The lower weight is setup incorrectly.");
                }
            }
        }

        return true;
    }

    ///
    // setRetention
    //
    // @param mixed the hours and costs as an array
    //
    public function setRetention($periods)
    {
        //
        if (!is_array($periods))
        {
                throw new InvalidArgumentException(
                    "setRetention expects to get an array");
        }
        if (count($periods) % 3 != 0)
        {
                throw new InvalidArgumentException(
                    "setRetention expects to get start, end & price, count of parameters is wrong.");
        }

        // Now check to see if the parameters are correctly typed.
        $count = 0;
        foreach ($periods as $key => $period)
        {
            if (($count == 0 || $count == 1) && !is_numeric($period))
            {
                throw new InvalidArgumentException(
                    "setRetention arg $key is not integer");
            }
            if ($count == 2 && !is_float($period))
            {
                throw new InvalidArgumentException(
                    "setRetention arg $key is not float");
            }

            // Increment the count of line types
            $count++;
            // Check if we have to go back to zero again.
            if ($count > 2)
            {
                $count = 0;
            }
        }

        foreach ($periods as $key => $period)
        {
            // Check if we have a surcharge line rather than a data one
            if ($period == -1)
            {
                $this->setRetentionSurcharge(
                            $periods[$key + 1],
                            $periods[$key + 2],
                            99999,
                            $periods[$key + 2]);
                // The surcharge line should always be the last line for the
                // client's data.
                break;
            }
            switch ($count)
            {
                case 0:
                    $this->retentionLower[]  = $period;
                    break;
                case 1:
                    $this->retentionHigher[] = $period;
                    break;
                case 2:
                    $this->retentionPrice[]  = $period;
                    break;
            }

            // Increment the count of line types
            $count++;
            // Check if we have to go back to zero again.
            if ($count > 2)
            {
                $count = 0;
            }
        }

        // Now check that the values entered are consistent
        for ($i = count($this->retentionHigher) - 1; $i >= 0; $i--)
        {
            if ($this->retentionHigher[$i] <= $this->retentionLower[$i])
            {
                $this->error->message("error",
                    "retention range is not setup correctly.");
                throw new InvalidArgumentException(
                    "retention range is not setup correctly");
            }

            if ($i > 0)
            {
                if ($this->retentionHigher[$i] <= $this->retentionHigher[$i - 1] ||
                    $this->retentionHigher[$i] <= $this->retentionLower[$i - 1])
                {
                    $this->error->message("error",
                        "retention range setup is not correct.");
                    throw new InvalidArgumentException(
                        "retention range setup is not correct");
                }

                if ($this->retentionLower[$i] < $this->retentionHigher[$i - 1])
                {
                    $this->error->message("error",
                        "A lower retention is setup incorrectly.");
                    throw new InvalidArgumentException(
                        "A lower retention is setup incorrectly.");
                }
            }
        }
    }

    ///
    // setRetentionSurcharge
    //
    // @param int   The number of hours
    // @param float The extra fee
    // @param int   The maximum number of hours after which other surcharge is
    //              added
    // @param float The maximum extra fee used when the maximum hours is exceeded
    //
    public function setRetentionSurcharge($hours, $price, $maxHours, $maxPrice)
    {
        if (!is_numeric($hours) || $hours == 0)
        {
            $this->error->message("error",
                "Parameter hours is not a number.");
            throw new InvalidArgumentException(
                "Parameter hours is not a number");
        }
        if (!is_numeric($maxHours) || $maxHours == 0)
        {
            $this->error->message("error",
                "Parameter maxHours is not a number.");
            throw new InvalidArgumentException(
                "Parameter maxHours is not a number");
        }
        if (!is_numeric($price) || $price == 0.0)
        {
            $this->error->message("error",
                "Parameter price is not a number.");
            throw new InvalidArgumentException(
                "Parameter price is not a number");
        }
        if (!is_numeric($maxPrice) || $maxPrice == 0.0)
        {
            $this->error->message("error",
                "Parameter maxprice is not a number.");
            throw new InvalidArgumentException(
                "Parameter maxprice is not a number");
        }

        $this->retentionHours         = $hours;
        $this->retentionSurcharge     = $price;
        $this->retentionMaxHours      = $maxHours;
        $this->retentionMaxHoursPrice = $maxPrice;

        return true;
    }

    ///
    // setSimpleRetentionSurcharge
    //
    public function setSimpleRetentionSurcharge($hours, $standard, $surcharge)
    {
        if (!is_numeric($hours) || !is_numeric($standard) ||
            !is_numeric($surcharge))
        {
            $this->error->message("error",
                "Parameter is not a number.");
            throw new InvalidArgumentException(
                "Parameter is not a number");
        }

        $this->rpHours     = $hours;
        $this->rpStandard  = $standard;
        $this->rpSurcharge = $surcharge;

        return true;
    }

    ///
    // processLine
    //
    // @brief Take a line of data and output the live with the price calculated
    // @param mixed The size/weight/retention hours to find a price for
    //
    public function processLine($data)
    {
        if ($this->calcType == 0 && count($this->size) == 0)
        {
            // User has selected size but has not given any prices.
            $this->error->message("error",
                "processLine size selected but no prices entered");

            throw new LogicException("processLine, you have seletced price by size but have provided no prices for size.");
        }

        if ($this->calcType == 1 && count($this->weightPrice) == 0)
        {
            // User has selected size but has not given any prices.
            $this->error->message("error",
                "processLine weight selected but no prices entered");

            throw new LogicException("processLine, you have seletced price by weight but have provided no prices for weights.");
        }

        if ($this->calcType == 2 && count($this->retentionPrice) == 0)
        {
            // User has selected size but has not given any prices.
            $this->error->message("error",
                "processLine weight selected but no prices entered");

            throw new LogicException("processLine, you have seletced price by weight but have provided no prices for weights.");
        }

        switch ($this->calcType)
        {
            case 0: // Size
                $data = strtoupper($data);

                switch ($data)
                {
                    case 'A':
                    case 'S':
                        return $this->size[0];
                        break;
                    case 'B':
                    case 'M':
                        return $this->size[1];
                        break;
                    case 'C':
                    case 'L':
                        return $this->size[2];
                        break;
                    default:
                        $this->error->message("error",
                            "no SIZE found for the parameter $data");
                        throw new InvalidArgumentException(
                            "no SIZE found for the parameter $data");
                        break;
                }
                break;
            case 1: // Weight
                $row = -1;

                for ($i = count($this->weightRangeHigher) - 1; $i >= 0; $i--)
                {
                    // Search for the lowest & highest matching element.
                    if ($data >= $this->weightRangeLower[$i] &&
                        $data <  $this->weightRangeHigher[$i])
                    {
                        $row = $i;
                    }
                }

                if ($row == -1)
                {
                    // Failed to find a matching group
                    $this->error->message("warning",
                        "Failed to find a matching weight group for $data");
                    throw new UnexpectedValueException(
                        "Failed to find a matching weight group for $data");
                }

                return $this->weightPrice[$row];
                break;
            case 2: // Retention
                // Has the parcel been in the locker for a very long time?
                if ($data > $this->retentionMaxHours)
                {
                    return $this->retentionMaxHoursPrice;
                }

                $row = -1;

                for ($i = count($this->retentionHigher) - 1; $i >= 0; $i--)
                {
                    // Search for the lowest & highest matching element.
                    if ($data >= $this->retentionLower[$i] &&
                        $data <  $this->retentionHigher[$i])
                    {
                        $row = $i;
                    }
                }

                if ($row == -1 && $data <= $this->retentionHours)
                {
                    // Failed to find a matching group
                    $this->error->message("error",
                        "Failed to find a matching retention group for $data");
                    throw new UnexpectedValueException(
                        "Failed to find a matching retention group for $data");
                }

                $extra = 0.0;
                $hours = 0;

                if ($this->retentionHours != 0)
                {
                    // Check if the number of hours is greater than the free
                    // allowance.
                    if ($this->retentionHours < $data)
                    {
                        $hours = $data - $this->retentionHours;
                        $hours /= 24;
                        $hours = floor($hours);

                        //echo "hours = " . $hours . "\n";

                        if ($hours < 0)
                        {
                            $hours = 0;
                        }

                        $extra = $this->retentionSurcharge * $hours;
                    }
                }

                if ($row == -1)
                {
                    return $this->retentionSurcharge +
                        $hours * $this->retentionSurcharge;
                }
                else
                {
                    return $this->retentionPrice[$row] + $extra;
                }
                break;
            case 3: // SIMPLE Surcharge
                $diff = 0;
                $days = 0;

                // Check if the period is greater than the maximum normal.
                if ($data > $this->rpHours)
                {
                    $diff = $data - $this->rpHours;
                    $diff /= 24;
                    $diff = ceil($diff);
                    $days = ceil($this->rpHours / 24);
                }
                else
                {
                    $days = ceil($data / 24);
                    if ($days == 0)
                    {
                        // Charge them for something.
                        $days = 1;
                    }
                }

                return $this->rpStandard * $days +
                        ($diff * $this->rpSurcharge);

                break;
        }
    }
}

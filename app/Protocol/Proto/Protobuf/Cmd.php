<?php
# Generated by the protocol buffer compiler.  DO NOT EDIT!
# source: cmd.proto

namespace App\Protocol\Proto\Protobuf;

use UnexpectedValueException;

/**
 * Protobuf type <code>netsvrBusinessDemo.cmd.Cmd</code>
 */
class Cmd
{
    use \NetsvrBusiness\Contract\RouterAndDataForProtobufTrait;
    use \NetsvrBusiness\Contract\RouterAndDataForProtobufTrait;
    /**
     * Generated from protobuf enum <code>Default = 0;</code>
     */
    const PBDefault = 0;
    /**
     * Generated from protobuf enum <code>Broadcast = 1;</code>
     */
    const Broadcast = 1;
    /**
     * Generated from protobuf enum <code>SingleCast = 2;</code>
     */
    const SingleCast = 2;

    private static $valueToName = [
        self::PBDefault => 'Default',
        self::Broadcast => 'Broadcast',
        self::SingleCast => 'SingleCast',
    ];

    public static function name($value)
    {
        if (!isset(self::$valueToName[$value])) {
            throw new UnexpectedValueException(sprintf(
                    'Enum %s has no name defined for value %s', __CLASS__, $value));
        }
        return self::$valueToName[$value];
    }


    public static function value($name)
    {
        $const = __CLASS__ . '::' . strtoupper($name);
        if (!defined($const)) {
            $pbconst =  __CLASS__. '::PB' . strtoupper($name);
            if (!defined($pbconst)) {
                throw new UnexpectedValueException(sprintf(
                        'Enum %s has no value defined for name %s', __CLASS__, $name));
            }
            return constant($pbconst);
        }
        return constant($const);
    }
}

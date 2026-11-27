# Limitations

If you customized JMS Serializer with your own listeners or similar things, this serializer will not work for you. We made an effort to detect when unsupported features are used and raise an error, but recommend that you double check whether the rebuy Serializer really produces the exact same as JMS when transforming your data.

JMS serializer groups and versions are supported for serializing but not for deserializing.

## SkipWhenEmpty

`SkipWhenEmpty` skips a property when it is `null`, an empty array or an empty `Countable` (e.g. a Doctrine `Collection`). Unlike JMS, an object that serializes to no properties is still written as an empty object.

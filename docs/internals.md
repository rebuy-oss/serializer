# Internals

## How it works
The rebuy Serializer generates PHP code based on the PHP models that you specify. It uses the flexible `rebuy/metadata-parser` to gather metadata on the models. A separate file is generated for every version and serializer groups combination to move all logic to the code generation step. This serializer is fast even for massive object trees because the generated PHP code is very simplistic and specific to the usecase, rather than the complex, flexible callback structure that JMS serializer uses. The project we developed this for often has data with up to a megabyte of compact JSON data.

## Background: Why an Object Serializer Generator?
We started having performance problems with large object structures (often
several Megabyte of JSON data). Code analysis showed that a lot of time is
spent calling the JMS callback structure hundred thousands of times.
Simplistic generated PHP code is much more efficient at runtime.

## Implementation Notes
The `DeserializerGenerator` and `SerializerGenerator` produce PHP code from the
metadata. The generators use twig to render the PHP code, for better
readability. See the `Template` namespace.

The indentation in the generated code is not respecting levels of nesting. We
could carry around the depth and prepend whitespace, but apart from debugging,
nobody will look at the generated code.

We decided to not use reflection, for better performance. Properties need to be
public or have a public getter for serialization. For deserialization, we also
match constructor arguments by name, so as long as a non-public property name
matches a constructor argument, it needs no setter.

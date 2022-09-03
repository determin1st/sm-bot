<details>
<summary>definitions</summary>

## bot
A computer program that has an identity (character)
and acts for the user and by the user
in relationship with the agency (in the middle).

Agency requests initiated by the end user
are passed to the bot for servicing.

## multibot
Multiple bots running on the same machine
compose a multibot.

These bots serve the same purpose (domain),
share codebase and often run
in a single (asynchroneous) process.

Requests are distributed and
applied to the separate data stores.

Rarely, data is common, for the only purpose of avoiding
agency limits (content rules).

## masterbot
Masterbot acts as a bot supervisor,
...
it manages other bots which run as separate processes (or threads),
may or may not share codebase or data.

</details>
<details>
<summary>concept specs</summary>

## command
```

     command = <path><func?><args?> | <func><args?>

        path = /<name><path?>
        func = !<name>
        args = <whitespace><string>

        name = string:[a-z0-9_]

```
## markup
```
      markup = [<row>,..] | <stateMarkup>
         row = [<cell>,..]
        cell = <command> | <child>

       child = <name> of child item

 stateMarkup = [<state>:<markup>,..]
       state = <name> of current state of the item

```

[//]: # (fold start{{{)

content

[//]: # (}}})


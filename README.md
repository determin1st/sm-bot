<details>
<summary>definitions</summary>

## bot
A computer program that acts for the user and by the user
in relationship with the agency -
telegram - a freeware instant messaging service.
Agency requests initiated by the end user
are passed to the bot for servicing.

## multibot
Multiple bots running on the same machine, compose a multibot.
These bots serve the same purpose, share codebase and
often run in a single (asynchroneous) process.
Requests are distributed to the separate data stores.
Rarely data is shared, for the only purpose of avoiding
agency limits (content rules).

## masterbot
Masterbot acts as a bot supervisor.
It creates, deletes, starts, stops and manages other bots.

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


<details>
<summary>definitions</summary>

## bot
A computer program that has an identity (character)
and acts for the user and by the user
in relationship with the agency is called a bot.

Agency requests initiated by the end user
are passed to the bot for servicing.

## multibot
Multiple bots running on the same machine
compose a multibot.

These bots serve the same purpose (domain),
share their codebase and often run
in a single (asynchroneous) process.

Requests are distributed internally by identifier and
applied to the separate data stores.

Rarely, data is shared for the only purpose of avoiding
agency limits (content rules).

## masterbot
Masterbot acts as a bot supervisor, it creates new bots,
changes their configuration and state,
displays information about them,
thus, manages them in a common way.

Masterbot spawns run separately from each other
(either processes or threads) and
may or may not share codebase or data,
but they are rigid to the master's command via IPC.

## sm-bot
A state machine bot framework includes
management console process which
aggregates bot logs and starts the masterbot.

The console may safely be closed,
leaving bots running, or,
it can close with bots altogether.


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


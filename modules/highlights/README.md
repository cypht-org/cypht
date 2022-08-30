## Highlights

This module set allows you to setup custom color highlighting for
message lists

## Quick background

> A user wanted to have a fast overview of his (important) emails from some of his accounts in his inbox. As he added 9 accounts, it was sometimes a bit messy in his inbox. To keep him informed it would be great to have a color attached to each of his accounts that gets applied as a background (or marker or anything else) and thus see to which account the incoming mail belongs to.

This being as important as it is interesting, we decided to add the **`Highlight Module Set`**

## How to use it?

To use the Highlight module set you have to configure how you want it to work for you, and this can be done by selecting **`Highlights`** under **`Settings`**, and adding a "**`Rule`**" which will be applied accordingly.

### How to define a Rule

To define a rule you need to specify a couple of things:

- **Source type**: Select "_`E-mail`_"
- **Flags**: This is very important as it's where you choose which type of email (flag), in a specific account you want to apply your rule to. A flag can be _`Unseen`_, _`Seen`_, _`Flagged`_, _`Deleted`_, or _`Answered`_.\
  This is optional though. Which means if no flag is selected, the rule will be applied to all the emails of the account no matter whether they are unseen, seen, flagged, deleted, or answered.
- **Accounts**: Here you choose to which one of your accounts you want to apply the rule. (Note that you can select more than one account)
- **Highlight target**: Here you select the "target" of your rule. You can either select Text or Background.
- **Highlight Color**: Here is where you choose the color of your rule which will be applied as text-color or as background color depending on what you chose for the highlight target
- **CSS override**: Choose if you want to override the style that may have already been applied

Note that your rules are listed under Existing Rules and you can still delete them and define new ones.

# How to contribute

Thank you for choosing Cypht webmail and for your willingness to contribute to its development! Your support helps make it better for everyone. There are many ways to get involved, regardless of your technical skill level: you can **report a bug** you've encountered, **propose an exciting new feature**, submit a **code fix** for an existing issue, or **improve our documentation** to make it clearer for other users. Every contribution, big or small, is valued and makes a real difference to the project and our community.

## Reporting Bugs

Bugs are tracked as [GitHub issues](https://github.com/cypht-org/cypht/issues). Before creating a new issue:
- **Search existing issues** to see if the problem has already been reported.
- If it's a new bug, **create a new issue**.

Please provide the following information in your bug report:
- A clear, descriptive title.
- A detailed description of the behavior and what you expected to happen instead.
- Steps to reproduce the problem.
- Your environment: OS (name and version), Browser, and any other relevant details.

## Suggesting new features

We welcome ideas for new features and improvements. For any new proposed feature, you need to create also a **new issue**. This allows maintainers and the community to discuss the approach before you write any code. Before submitting the issue:
-  **search existing ideas** to avoid duplicates,
-  **Provide a clear description** of the proposed feature and the problem it solves. 

## Submit a bug fix

Always link your code changes to a GitHub issue. If one doesn't exist for your change, please create it first. Next you will need to follow the Git workflow.

## Git contribution workflow

1. Fork the Official Repository

Navigate to the official Cypht repository on GitHub (https://github.com/cypht-org/cypht) and click the "Fork" button in the top-right corner.

2. Clone Your Fork Locally

```
git clone https://github.com/YOUR_USERNAME/cypht.git
cd cypht
```

3. Configure Remote Repositories (origin & upstream)

```
# Check the existing remote name (it should be 'origin' pointing to your fork)
git remote -v

# Add the official Cypht repository as the 'upstream' remote
git remote add upstream https://github.com/cypht-org/cypht.git

# Verify both remotes are set correctly
git remote -v
```

4. Create a new Branch (for the new Feature or a bug fix)

```
git checkout -b feature-amazing-new-feature
# or
git checkout -b fix-issue-123-description
```

5. Write Code and run tests

**Code **

This is the core development phase where you implement your change. Before writing a line of code, take a moment to explore Cypht's architecture and code style.

Make changes that are minimal and specific to the issue you are solving. Avoid unnecessary refactoring or style changes in unrelated code.

Match the existing code style (indentation, bracket placement, naming conventions for variables and functions). This makes your code feel like a natural part of the project.

Add clear comments for complex logic, but strive to write code that is self-documenting through good variable and function names.

**Manual test**

Manual testing is your first line of defense to catch obvious issues and logic errors. **Do not skip this**. 

Example:

**For a Backend/Bug Fix** (e.g., fixing IMAP connection errors):

- **Reproduce the Bug**: First, confirm you can reproduce the original issue described on GitHub.

- **Test the Fix**: Apply your change and verify the specific error no longer occurs.

- **Check for Regressions**: This is vital. Does your fix break any other existing functionality?

    - Can you still read emails?
    - Can you still send emails?
    - Do all the other modules still load correctly?

- **Test with Different Configurations**: If possible, test with different mail servers to ensure robustness.

**Write Automated tests (for the new feature... if possible)**

6. Run automated tests (if possible)

 - Unit Tests and integration tests with PHPUnit
 - End to end tests with Selenium

You can learn about how to run these tests [here](https://www.cypht.org/developers-documentation/) in the **Run Tests** section.

7. Commit Changes

Stage and commit your changes with clear, descriptive messages.

```
# Stage changes
git add .

# Commit changes (be specific!)
git commit -m "feat(backend): allow search flagged in all folders if enabled in settings"
```

8. Push to Your Fork and Create a Pull Request (PR)

```
git push -u origin feature-allow-flagged-emails-search
```

Go to your fork on GitHub. You will see a prompt to "Compare & pull request" for the newly pushed branch. Click it.

Fill out the PR template:

- Title: Clear summary
- Description: Detail what you did, why you did it, and how it can be tested (if possible).


**We appreciate all feedback and support of the project.**

If you have questions, please join our chat at: https://gitter.im/cypht-org/community

$(function () {
    function focusElement(elem) {
        elem.focus();
        elem.select();
    }

    function validateInput(input) {
        if (isNaN(Number(input.value)) || input.value === "") {
            input.classList.add("invalid");
            return false;
        }
        input.classList.remove("invalid");
        return input.value.length > 1 ? input.value[0] : input.value;
    }

    function focusNextInput(currentInput) {
        if (currentInput.nextElementSibling) {
            focusElement(currentInput.nextElementSibling);
        }
    }

    function focusPrevInput(currentInput) {
        if (currentInput.previousElementSibling) {
            focusElement(currentInput.previousElementSibling);
        }
    }

    function handleArrowKeys(e) {
        if (e.key == "ArrowRight") {
            e.preventDefault();
            focusNextInput(e.target);
        }
        if (e.key == "ArrowLeft") {
            e.preventDefault();
            focusPrevInput(e.target);
        }
    }

    function handleBackspace(e) {
        if (e.key == "Backspace") {
            e.target.value = "";
            e.target.classList.remove("invalid");
            focusPrevInput(e.target);
        }
    }

    function getInputCode() {
        let inputCode = "";
        for (let input of $(".tfa_confirmation_input_digit")) {
            input.classList.remove("invalid");
            if (validateInput(input) === false) {
                return null;
            }
            inputCode += input.value;
            input.blur();
        }
        return inputCode;
    }

    function tFaInit() {
        let verified = false;

        const formInput = $('input[name="2fa_enable"]');
        const tfaEnabled = formInput.is(":checked");
        const confirmationBtn = $("#tfaConfirmationBtn");
        const formContainer = $(".tfa_confirmation_form");

        if (!tfaEnabled) {
            formInput.on("change", function () {
                const checked = $(this).is(":checked");

                if (checked && !verified) {
                    $(this).prop("checked", false);
                    Hm_Notices.show('You need to verify your 2 factor authentication code before processing', 'warning');
                    return;
                }
            });
        }

        formContainer.on("input", function (e) {
            const value = validateInput(e.target);
            if (value !== false) {
                e.target.value = value;
                focusNextInput(e.target);
            } else {
                e.target.value = "";
            }
        });

        formContainer.on("click", function (e) {
            if (e.target.tagName == "INPUT") {
                focusElement(e.target);
            }
        });

        formContainer.on("keydown", ".tfa_confirmation_input_digit", function (e) {
            handleArrowKeys(e);
            handleBackspace(e);
        });

        formContainer.on("paste", function (e) {
            const pastedData = e.originalEvent.clipboardData.getData("text");

            if (pastedData === "") {
                return;
            }
            const payloadData = pastedData.split("");

            const inputs = $(".tfa_confirmation_input_digit");

            inputs.each(function (i, input) {
                $(input).val(payloadData[i]).focus();
            });

            const tm = setTimeout(() => {
                for (let input of inputs) {
                    if (validateInput(input) === false) {
                        focusElement(input);
                        break;
                    }
                }
                clearTimeout(tm);
            }, 0);
        });

        confirmationBtn.on("click", function (e) {
            e.preventDefault();
            $(this).removeClass("invalid").removeClass("shake");
            $(".tfa_confirmation_input_digit").removeClass("invalid");
            var code = getInputCode();

            if (!code) {
                Hm_Notices.show("You need to enter the verification code", 'warning');
                return;
            }

            $(this).text("Processing").addClass("loading");
            Hm_Ajax.request(
                [
                    { name: "hm_ajax_hook", value: "ajax_2fa_setup_check" },
                    { name: "2fa_code", value: code },
                ],
                function (response) {
                    if (response && response.ajax_2fa_verified) {
                        verified = true;
                        formInput.prop("checked", true);
                        confirmationBtn.addClass("valid");
                        Hm_Notices.show("2 factor authentication enabled");
                    } else {
                        verified = false;
                        formInput.prop("checked", false);
                        $(".tfa_confirmation_input_digit").addClass("invalid");
                        confirmationBtn.addClass("invalid").addClass("shake");
                        Hm_Notices.show("2 factor authentication code does not match", 'danger');
                    }
                    confirmationBtn.text("Verify code").removeClass("loading");
                }
            );
        });
    }

    tFaInit();
});

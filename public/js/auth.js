// Auth page functionality with AJAX
document.addEventListener("DOMContentLoaded", () => {
  initializeAuthPages()
})

function initializeAuthPages() {
  // Initialize all auth functionality
  initializePasswordToggle()
  initializeRoleDropdown()
  initializeFormValidation()
  initializeRememberMe()
  initializeAjaxForms()

  // Focus on first input
  focusFirstInput()
}

// Focus on first input field
function focusFirstInput() {
  const firstInput = document.querySelector(".form-input")
  if (firstInput) {
    firstInput.focus()
  }
}

// Password toggle functionality
function initializePasswordToggle() {
  const passwordToggles = document.querySelectorAll(".password-toggle")

  passwordToggles.forEach((toggle) => {
    toggle.addEventListener("click", function (e) {
      e.preventDefault()

      const input = this.previousElementSibling
      const isVisible = input.type === "text"

      // Toggle input type
      input.type = isVisible ? "password" : "text"

      // Toggle button state
      this.classList.toggle("show", !isVisible)

      // Focus back to input
      input.focus()
    })
  })
}

// Role dropdown functionality
function initializeRoleDropdown() {
  const dropdownBtn = document.getElementById("roleDropdownBtn")
  const dropdownMenu = document.getElementById("roleDropdownMenu")
  const selectedRole = document.getElementById("selectedRole")
  const roleInput = document.getElementById("roleInput")
  const roleOptions = document.querySelectorAll(".role-option")

  if (!dropdownBtn || !dropdownMenu) return

  // Toggle dropdown
  dropdownBtn.addEventListener("click", (e) => {
    e.preventDefault()
    dropdownMenu.classList.toggle("show")
  })

  // Handle role selection
  roleOptions.forEach((option) => {
    option.addEventListener("click", function () {
      const role = this.dataset.role
      const roleName = this.textContent.trim()

      // Update UI
      selectedRole.textContent = roleName
      roleInput.value = role

      // Close dropdown
      dropdownMenu.classList.remove("show")
    })
  })

  // Close dropdown when clicking outside
  document.addEventListener("click", (e) => {
    if (!dropdownBtn.contains(e.target) && !dropdownMenu.contains(e.target)) {
      dropdownMenu.classList.remove("show")
    }
  })

  // Close dropdown on escape key
  document.addEventListener("keydown", (e) => {
    if (e.key === "Escape") {
      dropdownMenu.classList.remove("show")
    }
  })
}

// Form validation
function initializeFormValidation() {
  const forms = document.querySelectorAll(".auth-form")

  forms.forEach((form) => {
    // Real-time validation
    const inputs = form.querySelectorAll(".form-input")
    inputs.forEach((input) => {
      input.addEventListener("blur", function () {
        validateField(this)
      })

      input.addEventListener("input", function () {
        // Remove error state when user starts typing
        if (this.classList.contains("error")) {
          this.classList.remove("error")
          clearFieldError(this)
        }
      })
    })
  })
}

// Validate individual field
function validateField(input) {
  const value = input.value.trim()
  let isValid = true
  let errorMessage = ""

  // Clear previous validation states
  input.classList.remove("error", "success")
  clearFieldError(input)

  // Required field validation
  if (input.hasAttribute("required") && !value) {
    isValid = false
    errorMessage = "Field ini wajib diisi"
  }
  // Email validation
  else if (input.type === "email" && value) {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/
    if (!emailRegex.test(value)) {
      isValid = false
      errorMessage = "Format email tidak valid"
    }
  }
  // Password validation
  else if (input.name === "password" && value && value.length < 6) {
    isValid = false
    errorMessage = "Password minimal 6 karakter"
  }
  // Confirm password validation
  else if (input.name === "confirm_password" && value) {
    const passwordInput = document.querySelector('input[name="password"]')
    if (passwordInput && value !== passwordInput.value) {
      isValid = false
      errorMessage = "Konfirmasi password tidak sama"
    }
  }

  if (!isValid) {
    input.classList.add("error")
    showFieldError(input, errorMessage)
  } else if (value) {
    input.classList.add("success")
  }

  return isValid
}

// Show field error
function showFieldError(input, message) {
  clearFieldError(input)

  const errorDiv = document.createElement("div")
  errorDiv.className = "field-error"
  errorDiv.style.cssText = `
    color: #dc2626;
    font-size: 12px;
    margin-top: 4px;
    display: flex;
    align-items: center;
    gap: 4px;
  `
  errorDiv.innerHTML = `
    <svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor">
      <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
    </svg>
    ${message}
  `

  input.parentNode.appendChild(errorDiv)
}

// Clear field error
function clearFieldError(input) {
  const existingError = input.parentNode.querySelector(".field-error")
  if (existingError) {
    existingError.remove()
  }
}

// Remember me functionality
function initializeRememberMe() {
  const rememberCheckbox = document.querySelector('input[name="remember_me"]')
  const emailInput = document.querySelector('input[name="email"]')

  if (!rememberCheckbox || !emailInput) return

  // Load saved email on page load
  const savedEmail = localStorage.getItem("remembered_email")
  if (savedEmail) {
    emailInput.value = savedEmail
    rememberCheckbox.checked = true
  }
}

// Initialize AJAX forms
function initializeAjaxForms() {
  const loginForm = document.getElementById("loginForm")
  const registerForm = document.getElementById("registerForm")

  if (loginForm) {
    loginForm.addEventListener("submit", handleLoginSubmit)
  }

  if (registerForm) {
    registerForm.addEventListener("submit", handleRegisterSubmit)
  }
}

// Handle login form submission
async function handleLoginSubmit(e) {
  e.preventDefault()

  const form = e.target
  const formData = new FormData(form)
  const submitBtn = form.querySelector("#loginBtn")

  // Validate form
  if (!validateForm(form)) {
    return
  }

  // Show loading state
  setButtonLoading(submitBtn, true, "Logging in...")

  try {
    const response = await fetch("/login", {
      method: "POST",
      body: formData,
      headers: {
        "X-Requested-With": "XMLHttpRequest",
      },
    })

    const result = await response.json()

    if (result.success) {
      // Handle remember me
      const rememberCheckbox = form.querySelector('input[name="remember_me"]')
      const emailInput = form.querySelector('input[name="email"]')

      if (rememberCheckbox && rememberCheckbox.checked && emailInput) {
        localStorage.setItem("remembered_email", emailInput.value)
      } else {
        localStorage.removeItem("remembered_email")
      }

      showMessage("success", "Login berhasil! Mengalihkan...")

      // Redirect after short delay
      setTimeout(() => {
        window.location.href = result.redirect || "/dashboard"
      }, 1000)
    } else {
      showMessage("error", result.message || "Login gagal. Silakan coba lagi.")
      setButtonLoading(submitBtn, false)
    }
  } catch (error) {
    console.error("Login error:", error)
    showMessage("error", "Terjadi kesalahan. Silakan coba lagi.")
    setButtonLoading(submitBtn, false)
  }
}

// Handle register form submission
async function handleRegisterSubmit(e) {
  e.preventDefault()

  const form = e.target
  const formData = new FormData(form)
  const submitBtn = form.querySelector("#registerBtn")

  // Validate form
  if (!validateForm(form)) {
    return
  }

  // Additional password confirmation check
  const password = form.querySelector('input[name="password"]').value
  const confirmPassword = form.querySelector('input[name="confirm_password"]').value

  if (password !== confirmPassword) {
    showMessage("error", "Password dan konfirmasi password tidak sama!")
    return
  }

  // Check terms agreement
  const termsCheckbox = form.querySelector('input[name="terms"]')
  if (!termsCheckbox.checked) {
    showMessage("error", "Anda harus menyetujui syarat dan ketentuan!")
    return
  }

  // Show loading state
  setButtonLoading(submitBtn, true, "Creating...")

  try {
    const response = await fetch("/register", {
      method: "POST",
      body: formData,
      headers: {
        "X-Requested-With": "XMLHttpRequest",
      },
    })

    const result = await response.json()

    if (result.success) {
      showMessage("success", "Registrasi berhasil! Mengalihkan ke halaman login...")

      // Redirect to login after short delay
      setTimeout(() => {
        window.location.href = "/login"
      }, 2000)
    } else {
      showMessage("error", result.message || "Registrasi gagal. Silakan coba lagi.")
      setButtonLoading(submitBtn, false)
    }
  } catch (error) {
    console.error("Register error:", error)
    showMessage("error", "Terjadi kesalahan. Silakan coba lagi.")
    setButtonLoading(submitBtn, false)
  }
}

// Validate entire form
function validateForm(form) {
  let isValid = true
  const inputs = form.querySelectorAll(".form-input[required]")

  inputs.forEach((input) => {
    if (!validateField(input)) {
      isValid = false
    }
  })

  if (!isValid) {
    // Focus on first error field
    const firstError = form.querySelector(".form-input.error")
    if (firstError) {
      firstError.focus()
    }
  }

  return isValid
}

// Set button loading state
function setButtonLoading(button, loading, loadingText = "Loading...") {
  const btnText = button.querySelector(".btn-text")
  const btnLoading = button.querySelector(".btn-loading")
  const btnArrow = button.querySelector(".btn-arrow")

  if (loading) {
    button.disabled = true
    btnText.style.display = "none"
    btnArrow.style.display = "none"
    btnLoading.style.display = "flex"
    btnLoading.querySelector("span").textContent = loadingText
  } else {
    button.disabled = false
    btnText.style.display = "block"
    btnArrow.style.display = "block"
    btnLoading.style.display = "none"
  }
}

// Show message
function showMessage(type, message) {
  const messageContainer = document.getElementById("messageContainer")

  messageContainer.innerHTML = `
    <div class="message ${type}">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
        ${getMessageIcon(type)}
      </svg>
      <span>${message}</span>
    </div>
  `

  messageContainer.style.display = "block"

  // Auto hide success messages
  if (type === "success") {
    setTimeout(() => {
      messageContainer.style.display = "none"
    }, 5000)
  }

  // Scroll to message
  messageContainer.scrollIntoView({ behavior: "smooth", block: "nearest" })
}

// Get message icon based on type
function getMessageIcon(type) {
  const icons = {
    success: '<path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>',
    error: '<path d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/>',
    warning:
      '<path d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.34 16.5c-.77.833.192 2.5 1.732 2.5z"/>',
    info: '<path d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>',
  }
  return icons[type] || icons.info
}

// Utility function for password toggle (can be called from templates)
function togglePassword(inputId) {
  const input = document.getElementById(inputId)
  const button = input.nextElementSibling

  if (input.type === "password") {
    input.type = "text"
    button.classList.add("show")
  } else {
    input.type = "password"
    button.classList.remove("show")
  }
}

// Format number to Indonesian format
function formatNumber(number) {
  return new Intl.NumberFormat("id-ID").format(number)
}

// Format date to Indonesian format
function formatDate(dateString) {
  const date = new Date(dateString)
  const months = ["Jan", "Feb", "Mar", "Apr", "Mei", "Jun", "Jul", "Agu", "Sep", "Okt", "Nov", "Des"]

  const day = date.getDate()
  const month = months[date.getMonth()]
  const year = date.getFullYear()

  return `${day} ${month} ${year}`
}

// Export functions for global use
window.togglePassword = togglePassword
window.formatNumber = formatNumber
window.formatDate = formatDate

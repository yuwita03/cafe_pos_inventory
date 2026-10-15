<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { nextTick, ref } from 'vue';
import '../../../css/admin.css';
import { store } from '@/actions/App/Http/Controllers/Auth/SessionController';
import AppIcon from '@/components/AppIcon.vue';
import BaseButton from '@/components/ui/BaseButton.vue';

defineProps<{ status?: string | null }>();
const form = useForm({ email: '', password: '' });
const emailInput = ref<HTMLInputElement | null>(null);
const passwordInput = ref<HTMLInputElement | null>(null);
const showPassword = ref(false);

function login(): void {
    if (form.processing) {
        return;
    }
    form.post(store.url(), {
        onFinish: () => form.reset('password'),
        onError: async (errors) => {
            await nextTick();
            (errors.email ? emailInput : passwordInput).value?.focus();
        },
    });
}
</script>

<template>
    <div class="admin-shell login-page" lang="id">
        <Head title="Masuk staf" />
        <main class="login-content" aria-labelledby="login-title">
            <section class="login-introduction" aria-labelledby="staff-title">
                <div class="brand login-brand">
                    <span class="brand-mark"
                        ><AppIcon name="coffee" :size="25"
                    /></span>
                    <div>Café POS<span>Ruang kerja staf</span></div>
                </div>
                <div class="staff-description">
                    <p class="login-eyebrow">KHUSUS TIM CAFÉ</p>
                    <h2 id="staff-title">
                        Hari yang lancar dimulai dari sini.
                    </h2>
                    <p class="staff-summary">
                        Ruang kerja bersama untuk mengelola menu, memantau stok,
                        dan menjalankan operasional café sehari-hari.
                    </p>
                    <div class="staff-access">
                        <AppIcon name="staff" :size="21" />
                        <p>
                            <strong>Untuk owner dan staf</strong>Masuk dengan
                            akun yang disediakan oleh pengelola café.
                        </p>
                    </div>
                </div>
                <p class="staff-footer">Satu tim. Satu ruang kerja.</p>
            </section>

            <section class="login-panel" aria-labelledby="login-title">
                <div class="login-form-content">
                    <div class="login-heading">
                        <p class="login-eyebrow">SELAMAT DATANG KEMBALI</p>
                        <h1 id="login-title">Masuk ke ruang kerja</h1>
                        <p>Gunakan email dan kata sandi akun staf Anda.</p>
                    </div>
                    <form
                        novalidate
                        :aria-busy="form.processing"
                        @submit.prevent="login"
                    >
                        <div class="field login-field">
                            <label for="email">Email</label>
                            <input
                                id="email"
                                ref="emailInput"
                                v-model.trim="form.email"
                                name="email"
                                type="email"
                                autocomplete="username"
                                inputmode="email"
                                autocapitalize="none"
                                :spellcheck="false"
                                placeholder="nama@cafe.com"
                                required
                                :readonly="form.processing"
                                :aria-invalid="Boolean(form.errors.email)"
                                :aria-describedby="
                                    form.errors.email
                                        ? 'email-error'
                                        : undefined
                                "
                                @input="form.clearErrors('email')"
                            />
                            <p
                                v-if="form.errors.email"
                                id="email-error"
                                class="login-field-error"
                                role="alert"
                            >
                                {{ form.errors.email }}
                            </p>
                        </div>
                        <div class="field login-field">
                            <label for="password">Kata sandi</label>
                            <div class="password-control">
                                <input
                                    id="password"
                                    ref="passwordInput"
                                    v-model="form.password"
                                    name="password"
                                    :type="showPassword ? 'text' : 'password'"
                                    autocomplete="current-password"
                                    placeholder="Masukkan kata sandi"
                                    required
                                    :readonly="form.processing"
                                    :aria-invalid="
                                        Boolean(form.errors.password)
                                    "
                                    :aria-describedby="
                                        form.errors.password
                                            ? 'password-error'
                                            : undefined
                                    "
                                    @input="form.clearErrors('password')"
                                />
                                <button
                                    type="button"
                                    class="password-toggle"
                                    aria-controls="password"
                                    :aria-label="
                                        showPassword
                                            ? 'Sembunyikan kata sandi'
                                            : 'Tampilkan kata sandi'
                                    "
                                    :aria-pressed="showPassword"
                                    @click="showPassword = !showPassword"
                                >
                                    {{
                                        showPassword
                                            ? 'Sembunyikan'
                                            : 'Tampilkan'
                                    }}
                                </button>
                            </div>
                            <p
                                v-if="form.errors.password"
                                id="password-error"
                                class="login-field-error"
                                role="alert"
                            >
                                {{ form.errors.password }}
                            </p>
                        </div>
                        <BaseButton
                            type="submit"
                            variant="primary"
                            class="login-submit"
                            :disabled="form.processing"
                        >
                            <span
                                v-if="form.processing"
                                class="login-spinner"
                                aria-hidden="true"
                            />
                            {{ form.processing ? 'Memproses…' : 'Masuk' }}
                            <AppIcon
                                v-if="!form.processing"
                                name="arrow"
                                :size="17"
                            />
                        </BaseButton>
                    </form>
                    <aside v-if="status" class="login-status" role="status">
                        <AppIcon name="info" :size="18" />
                        <p>{{ status }}</p>
                    </aside>
                    <p class="login-footer">
                        Café POS · Operasional sehari-hari, lebih tertata.
                    </p>
                </div>
            </section>
        </main>
    </div>
</template>

<style scoped>
.login-page {
    display: grid;
}
.login-content {
    display: grid;
    grid-template-columns: 1.05fr 1fr;
    width: 100%;
    min-height: 100dvh;
    background: var(--surface);
}
.login-introduction {
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    gap: 56px;
    min-width: 0;
    padding: clamp(32px, 5vw, 80px);
    border-right: 1px solid #f0e6dd;
    background: #fff6ee;
}
.login-brand {
    padding: 0;
}
.login-eyebrow {
    color: var(--primary);
    font-size: 11px;
    font-weight: 600;
    letter-spacing: 1.1px;
}
.staff-description h2 {
    max-width: 390px;
    margin-top: 16px;
    font-size: 44px;
    font-weight: 600;
    line-height: 1.12;
    letter-spacing: -1.6px;
    text-wrap: balance;
}
.staff-summary {
    max-width: 370px;
    margin-top: 22px !important;
    color: #62605b;
    font-size: 15px;
    line-height: 1.8;
}
.staff-access {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    max-width: 365px;
    margin-top: 32px;
    padding-top: 24px;
    border-top: 1px solid #e8dcd1;
    color: #62605b;
    font-size: 13px;
    line-height: 1.7;
}
.staff-access svg {
    margin-top: 3px;
    color: var(--primary);
}
.staff-access strong {
    display: block;
    margin-bottom: 4px;
    color: var(--ink);
    font-size: 14px;
    font-weight: 600;
}
.staff-footer {
    color: #62605b;
    font-size: 12px;
}
.login-panel {
    display: grid;
    align-items: center;
    min-width: 0;
    padding: clamp(32px, 5vw, 80px);
}
.login-form-content {
    width: 100%;
    max-width: 360px;
    margin: 0 auto;
}
.login-heading {
    margin-bottom: 28px;
}
.login-heading .login-eyebrow {
    margin-bottom: 10px;
}
.login-heading h1 {
    margin-bottom: 8px;
    font-size: 25px;
    font-weight: 600;
    line-height: 1.3;
    letter-spacing: -0.7px;
}
.login-heading > p:last-child {
    color: #616161;
}
.login-field {
    margin-bottom: 20px;
    color: var(--ink);
    font-size: 14px;
}
.login-field input {
    min-height: 48px;
    font-size: 16px;
}
.login-field input::placeholder {
    color: #73756e;
    font-size: 14px;
}
.login-field input[aria-invalid='true'] {
    border-color: #b91c1c;
}
.password-control {
    position: relative;
}
.password-control input {
    padding-right: 110px;
}
.password-toggle {
    position: absolute;
    top: 2px;
    right: 3px;
    min-height: 44px;
    padding: 0 9px;
    border: 0;
    border-radius: 5px;
    background: transparent;
    color: var(--primary);
    font-size: 12px;
    font-weight: 600;
}
.password-toggle:hover {
    background: var(--soft);
}
.login-field .login-field-error {
    margin-top: 6px;
    color: #b91c1c;
    font-size: 12px;
    font-weight: 400;
}
.login-submit {
    width: 100%;
    min-height: 48px;
    font-size: 14px !important;
}
.login-page .login-submit:disabled {
    opacity: 0.7;
}
.login-spinner {
    width: 16px;
    height: 16px;
    border: 2px solid #ffffff66;
    border-top-color: white;
    border-radius: 50%;
    animation: login-spin 700ms linear infinite;
}
.login-status {
    display: flex;
    align-items: flex-start;
    gap: 9px;
    margin-top: 20px;
    color: #616161;
    font-size: 12px;
    line-height: 1.65;
}
.login-status svg {
    margin-top: 2px;
}
.login-footer {
    margin-top: 28px !important;
    color: #616161;
    font-size: 12px;
    text-align: center;
}
@keyframes login-spin {
    to {
        transform: rotate(360deg);
    }
}
@media (max-width: 900px) {
    .login-introduction,
    .login-panel {
        padding: 32px;
    }
    .staff-description h2 {
        font-size: 36px;
    }
}
@media (max-width: 767px) {
    .login-content {
        grid-template-columns: minmax(0, 1fr);
    }
    .login-introduction {
        min-height: auto;
        gap: 32px;
        padding: 28px 24px;
        border-right: 0;
        border-bottom: 1px solid #f0e6dd;
    }
    .staff-description h2 {
        font-size: 32px;
        letter-spacing: -0.9px;
    }
    .staff-summary {
        margin-top: 16px !important;
        font-size: 14px;
    }
    .staff-access {
        margin-top: 24px;
        padding-top: 20px;
    }
    .login-panel {
        padding: 32px 24px;
    }
    .login-heading h1 {
        font-size: 23px;
    }
}
@media (prefers-reduced-motion: reduce) {
    .login-spinner {
        animation: none;
    }
}
</style>

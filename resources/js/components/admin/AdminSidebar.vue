<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed, ref, useId, watch } from 'vue';
import AppIcon from '@/components/AppIcon.vue';
import { adminNavigation } from '@/lib/adminNavigation';
const emit = defineEmits<{ navigate: [] }>();
const page = usePage();
const currentPath = computed(() => page.url.split('?')[0]);
const navigationId = useId();
const expanded = ref<string[]>([]);
const currentItem = computed(() =>
    adminNavigation
        .flatMap((group) => group.items)
        .find((item) => item.href.url() === currentPath.value),
);
watch(
    currentPath,
    (path) => {
        const activeGroup = adminNavigation.find((group) =>
            group.items.some((item) => item.href.url() === path),
        );
        if (activeGroup && !expanded.value.includes(activeGroup.label)) {
            expanded.value.push(activeGroup.label);
        }
    },
    { immediate: true },
);
function toggleGroup(label: string): void {
    expanded.value = expanded.value.includes(label)
        ? expanded.value.filter((item) => item !== label)
        : [...expanded.value, label];
}
</script>

<template>
    <div class="sidebar-inner">
        <div class="brand">
            <span class="brand-mark"><AppIcon name="coffee" :size="25" /></span>
            <div>Café POS<span>Ruang pengelolaan</span></div>
        </div>
        <div class="workspace-label">
            <span class="workspace-dot" />Admin workspace<span
                class="workspace-version"
                >V1</span
            >
        </div>
        <nav class="sidebar-navigation" aria-label="Navigasi admin">
            <template
                v-for="(group, index) in adminNavigation"
                :key="group.label"
            >
                <p v-if="!group.icon" class="nav-heading">{{ group.label }}</p>
                <button
                    v-else
                    type="button"
                    class="nav-item nav-group-toggle"
                    :class="{
                        'group-active': group.items.some(
                            (item) => item.href.url() === currentPath,
                        ),
                    }"
                    :aria-expanded="expanded.includes(group.label)"
                    :aria-controls="navigationId + '-' + index"
                    @click="toggleGroup(group.label)"
                >
                    <AppIcon :name="group.icon" />
                    <span>{{ group.label }}</span>
                    <AppIcon
                        name="arrow"
                        :size="14"
                        class="nav-group-arrow"
                        :class="{ expanded: expanded.includes(group.label) }"
                    />
                </button>
                <div
                    v-show="!group.icon || expanded.includes(group.label)"
                    :id="navigationId + '-' + index"
                    :class="{ 'nav-submenu': group.icon }"
                >
                    <Link
                        v-for="item in group.items"
                        :key="item.label"
                        :href="item.href()"
                        class="nav-item"
                        :class="{
                            'nav-child': group.icon,
                            selected: currentPath === item.href.url(),
                        }"
                        :aria-current="
                            currentPath === item.href.url() ? 'page' : undefined
                        "
                        @click="emit('navigate')"
                    >
                        <AppIcon
                            :name="item.icon"
                            :size="group.icon ? 17 : 20"
                        />
                        <span
                            >{{ item.label
                            }}<small>{{ item.description }}</small></span
                        >
                        <span
                            v-if="'placeholder' in item && item.placeholder"
                            class="nav-placeholder"
                            aria-label="Placeholder"
                            >UI</span
                        >
                    </Link>
                </div>
            </template>
        </nav>
        <div class="sidebar-bottom">
            <div class="prototype-note">
                <AppIcon name="info" :size="18" />
                <p>
                    <strong>{{
                        currentItem &&
                        ('prototype' in currentItem ||
                            'placeholder' in currentItem)
                            ? 'Pratinjau UI'
                            : 'Pengembangan lokal'
                    }}</strong
                    >{{
                        currentItem &&
                        ('prototype' in currentItem ||
                            'placeholder' in currentItem)
                            ? 'Data contoh atau placeholder. Belum ada aksi operasional nyata.'
                            : 'Data tersimpan. Login staf belum diaktifkan.'
                    }}
                </p>
            </div>
            <div class="sidebar-profile">
                <span class="avatar">O</span>
                <div>Owner<small>Profil contoh · Admin</small></div>
                <span class="profile-dot" />
            </div>
        </div>
    </div>
</template>

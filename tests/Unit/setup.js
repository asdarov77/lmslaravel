// Common test setup for unit tests
// Provides mocks for $route, $router, $store used across tests

import { vi } from 'vitest'

// Mock $route with common meta (breadcrumbs, titleKey, etc.)
const createRouteMock = (overrides = {}) => ({
  params: {},
  query: {},
  meta: {
    breadcrumbs: [],
    titleKey: '',
    ...overrides?.meta,
  },
  ...overrides,
})

// Mock $router
const createRouterMock = () => ({
  push: vi.fn(),
  back: vi.fn(),
  go: vi.fn(),
  replace: vi.fn(),
})

// Mock $store
const createStoreMock = (overrides = {}) => ({
  state: {},
  getters: {},
  dispatch: vi.fn().mockResolvedValue(undefined),
  commit: vi.fn(),
  ...overrides,
})

// Global mocks
global.$route = createRouteMock()
global.$router = createRouterMock()
global.$store = createStoreMock()

// Make helpers available globally for tests that need custom mocks
global.createRouteMock = createRouteMock
global.createRouterMock = createRouterMock
global.createStoreMock = createStoreMock
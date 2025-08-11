import { describe, it, expect } from 'vitest'

describe('Basic Test', () => {
  it('should run basic test', () => {
    expect(1 + 1).toBe(2)
  })

  it('should have mocked globals available', () => {
    expect(typeof navigateTo).toBe('function')
    expect(typeof useRuntimeConfig).toBe('function')
    expect(typeof $fetch).toBe('function')
  })
})
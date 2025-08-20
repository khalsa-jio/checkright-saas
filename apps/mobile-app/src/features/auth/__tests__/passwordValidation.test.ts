import { PasswordValidator } from '../utils/passwordValidation';

describe('PasswordValidator', () => {
  describe('validate', () => {
    it('should validate a strong password', () => {
      const result = PasswordValidator.validate('StrongPass123!');

      expect(result.isValid).toBe(true);
      expect(result.errors).toHaveLength(0);
      expect(result.requirements.minLength).toBe(true);
      expect(result.requirements.hasUppercase).toBe(true);
      expect(result.requirements.hasLowercase).toBe(true);
      expect(result.requirements.hasNumbers).toBe(true);
      expect(result.requirements.hasSpecialChars).toBe(true);
    });

    it('should reject a weak password', () => {
      const result = PasswordValidator.validate('weak');

      expect(result.isValid).toBe(false);
      expect(result.errors.length).toBeGreaterThan(0);
      expect(result.requirements.minLength).toBe(false);
    });

    it('should provide specific error messages', () => {
      const result = PasswordValidator.validate('short');

      expect(result.errors).toContain(
        'Password must be at least 8 characters long'
      );
      expect(result.errors).toContain(
        'Password must contain at least one uppercase letter'
      );
      expect(result.errors).toContain(
        'Password must contain at least one number'
      );
      expect(result.errors).toContain(
        'Password must contain at least one special character'
      );
    });
  });

  describe('getStrengthIndicator', () => {
    it('should return very weak for empty password', () => {
      const result = PasswordValidator.getStrengthIndicator('');

      expect(result.label).toBe('Very Weak');
      expect(result.progress).toBe(0);
    });

    it('should return strong for password meeting all requirements', () => {
      const result = PasswordValidator.getStrengthIndicator('StrongPass123!');

      expect(result.label).toBe('Strong');
      expect(result.progress).toBe(1);
    });
  });

  describe('validateConfirmation', () => {
    it('should return true for matching passwords', () => {
      expect(
        PasswordValidator.validateConfirmation('password', 'password')
      ).toBe(true);
    });

    it('should return false for non-matching passwords', () => {
      expect(
        PasswordValidator.validateConfirmation('password', 'different')
      ).toBe(false);
    });

    it('should return false for empty passwords', () => {
      expect(PasswordValidator.validateConfirmation('', '')).toBe(false);
    });
  });

  describe('getRequirements', () => {
    it('should return list of password requirements', () => {
      const requirements = PasswordValidator.getRequirements();

      expect(requirements).toContain('At least 8 characters long');
      expect(requirements).toContain('One uppercase letter (A-Z)');
      expect(requirements).toContain('One lowercase letter (a-z)');
      expect(requirements).toContain('One number (0-9)');
      expect(requirements).toContain('One special character (!@#$%^&*)');
    });
  });
});

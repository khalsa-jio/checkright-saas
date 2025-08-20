import { DeepLinkingService } from '../utils/deepLinking';

describe('DeepLinkingService', () => {
  describe('parseInvitationToken', () => {
    it('should extract token from valid invitation URL', () => {
      const url =
        'https://checkright.app/invitations/abcd1234567890abcdef1234567890abcdef1234567890abcdef1234567890ab/accept';
      const token = DeepLinkingService.parseInvitationToken(url);

      expect(token).toBe(
        'abcd1234567890abcdef1234567890abcdef1234567890abcdef1234567890ab'
      );
    });

    it('should return null for invalid URL', () => {
      const url = 'https://checkright.app/invalid/url';
      const token = DeepLinkingService.parseInvitationToken(url);

      expect(token).toBeNull();
    });

    it('should return null for URL with short token', () => {
      const url = 'https://checkright.app/invitations/shorttoken/accept';
      const token = DeepLinkingService.parseInvitationToken(url);

      expect(token).toBeNull();
    });
  });

  describe('isValidInvitationUrl', () => {
    it('should validate correct invitation URL format', () => {
      const url =
        'https://checkright.app/invitations/abcd1234567890abcdef1234567890abcdef1234567890abcdef1234567890ab/accept';

      expect(DeepLinkingService.isValidInvitationUrl(url)).toBe(true);
    });

    it('should reject invalid URL format', () => {
      const url = 'https://checkright.app/wrong/format';

      expect(DeepLinkingService.isValidInvitationUrl(url)).toBe(false);
    });
  });

  describe('generateInvitationUrl', () => {
    it('should generate correct invitation URL', () => {
      const token =
        'abcd1234567890abcdef1234567890abcdef1234567890abcdef1234567890ab';
      const url = DeepLinkingService.generateInvitationUrl(token);

      expect(url).toBe(
        'https://checkright.app/invitations/abcd1234567890abcdef1234567890abcdef1234567890abcdef1234567890ab/accept'
      );
    });

    it('should use custom base URL', () => {
      const token =
        'abcd1234567890abcdef1234567890abcdef1234567890abcdef1234567890ab';
      const baseUrl = 'https://custom.example.com';
      const url = DeepLinkingService.generateInvitationUrl(token, baseUrl);

      expect(url).toBe(
        'https://custom.example.com/invitations/abcd1234567890abcdef1234567890abcdef1234567890abcdef1234567890ab/accept'
      );
    });
  });
});

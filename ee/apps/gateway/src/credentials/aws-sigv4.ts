// AWS Signature Version 4 request signer (plan §5.3, Bedrock row). The
// implementation is shared with Den in @idexal-ee/utils/aws-sigv4.
export { amzDate, awsUriEncode, buildCanonicalRequest, deriveSigningKey, signAwsRequest } from "@idexal-ee/utils/aws-sigv4"
export type { AwsCredentials, SignableRequest, SignAwsRequestInput } from "@idexal-ee/utils/aws-sigv4"

export const bedrockService = "bedrock"
export const bedrockMantleService = "bedrock-mantle"

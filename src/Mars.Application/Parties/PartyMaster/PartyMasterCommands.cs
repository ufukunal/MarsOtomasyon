using Mars.Application.Foundation.Auditing;
using Mars.Application.Foundation.Authorization;
using Mars.Application.Foundation.Context;
using Mars.Application.Foundation.Idempotency;
using Mars.Application.Foundation.Results;
using Mars.Domain.Parties;

namespace Mars.Application.Parties.PartyMaster;

public enum PartyMasterMutationOutcome
{
    Succeeded = 1,
    PartyNotFound = 2,
    ChildNotFound = 3,
    DuplicateOperation = 4,
    StaleVersion = 5,
    StateConflict = 6,
    DeterministicConflict = 7,
    InvalidMerge = 8
}

public sealed record PartyMasterMutationPersistenceResult(
    PartyMasterMutationOutcome Outcome,
    Guid? EntityPublicId = null,
    string? State = null,
    long? Version = null,
    Guid? RelatedPublicId = null,
    long? RelatedVersion = null);

public sealed record PartyMasterMutationReceipt(
    Guid EntityPublicId,
    string EntityType,
    string State,
    long Version,
    string CorrelationId);

public sealed record PartyMergeReceipt(
    Guid SourcePartyPublicId,
    Guid SurvivorPartyPublicId,
    string SourceState,
    long SourceVersion,
    long SurvivorVersion,
    string CorrelationId);

public sealed record PartyMasterWriteContext(
    Guid CompanyId,
    IdempotencyOperation Idempotency,
    AuditEntry Audit);

public sealed record EditPartyIdentityWrite(
    Guid PartyPublicId,
    long ExpectedVersion,
    string LegalName,
    string? DisplayName,
    PartyMasterWriteContext Context);

public sealed record CreatePartyContactWrite(
    PartyContact Contact,
    PartyMasterWriteContext Context);

public sealed record ChangePartyContactStateWrite(
    Guid PartyPublicId,
    Guid ContactPublicId,
    long ExpectedVersion,
    PartyMasterRecordState TargetState,
    PartyMasterWriteContext Context);

public sealed record CreatePartyCommunicationPointWrite(
    PartyCommunicationPoint CommunicationPoint,
    PartyMasterWriteContext Context);

public sealed record ChangePartyCommunicationPointStateWrite(
    Guid PartyPublicId,
    Guid ContactPublicId,
    Guid CommunicationPointPublicId,
    long ExpectedVersion,
    PartyMasterRecordState TargetState,
    PartyMasterWriteContext Context);

public sealed record CreatePartyAddressWrite(
    PartyAddress Address,
    PartyMasterWriteContext Context);

public sealed record UpdatePartyAddressWrite(
    Guid PartyPublicId,
    Guid AddressPublicId,
    long ExpectedVersion,
    PartyAddressPurpose Purpose,
    string Country,
    string? City,
    string? District,
    string? PostalCode,
    string? Line1,
    string? Line2,
    string? Label,
    bool IsDefault,
    PartyMasterWriteContext Context);

public sealed record ChangePartyAddressStateWrite(
    Guid PartyPublicId,
    Guid AddressPublicId,
    long ExpectedVersion,
    PartyMasterRecordState TargetState,
    PartyMasterWriteContext Context);

public sealed record ChangePartyTaxIdentityStateWrite(
    Guid PartyPublicId,
    Guid TaxIdentityPublicId,
    long ExpectedVersion,
    PartyTaxIdentityState TargetState,
    PartyMasterWriteContext Context);

public sealed record CreatePartyExternalMappingWrite(
    PartyExternalMapping ExternalMapping,
    PartyMasterWriteContext Context);

public sealed record ChangePartyExternalMappingStateWrite(
    Guid PartyPublicId,
    Guid ExternalMappingPublicId,
    long ExpectedVersion,
    PartyMasterRecordState TargetState,
    PartyMasterWriteContext Context);

public sealed record MergePartyWrite(
    Guid SourcePartyPublicId,
    Guid SurvivorPartyPublicId,
    long SourceExpectedVersion,
    long SurvivorExpectedVersion,
    bool UseSourceIdentity,
    bool MoveSourceRoles,
    bool MoveSourceContacts,
    bool MoveSourceAddresses,
    bool MoveSourceTaxIdentities,
    bool MoveSourceExternalMappings,
    PartyMergeLineage Lineage,
    PartyMasterWriteContext Context);

public interface IPartyMasterMutationPersistence
{
    Task<PartyMasterMutationPersistenceResult> EditIdentityAsync(
        EditPartyIdentityWrite write,
        CancellationToken cancellationToken);

    Task<PartyMasterMutationPersistenceResult> CreateContactAsync(
        CreatePartyContactWrite write,
        CancellationToken cancellationToken);

    Task<PartyMasterMutationPersistenceResult> ChangeContactStateAsync(
        ChangePartyContactStateWrite write,
        CancellationToken cancellationToken);

    Task<PartyMasterMutationPersistenceResult> CreateCommunicationPointAsync(
        CreatePartyCommunicationPointWrite write,
        CancellationToken cancellationToken);

    Task<PartyMasterMutationPersistenceResult> ChangeCommunicationPointStateAsync(
        ChangePartyCommunicationPointStateWrite write,
        CancellationToken cancellationToken);

    Task<PartyMasterMutationPersistenceResult> CreateAddressAsync(
        CreatePartyAddressWrite write,
        CancellationToken cancellationToken);

    Task<PartyMasterMutationPersistenceResult> UpdateAddressAsync(
        UpdatePartyAddressWrite write,
        CancellationToken cancellationToken);

    Task<PartyMasterMutationPersistenceResult> ChangeAddressStateAsync(
        ChangePartyAddressStateWrite write,
        CancellationToken cancellationToken);

    Task<PartyMasterMutationPersistenceResult> ChangeTaxIdentityStateAsync(
        ChangePartyTaxIdentityStateWrite write,
        CancellationToken cancellationToken);

    Task<PartyMasterMutationPersistenceResult> CreateExternalMappingAsync(
        CreatePartyExternalMappingWrite write,
        CancellationToken cancellationToken);

    Task<PartyMasterMutationPersistenceResult> ChangeExternalMappingStateAsync(
        ChangePartyExternalMappingStateWrite write,
        CancellationToken cancellationToken);

    Task<PartyMasterMutationPersistenceResult> MergeAsync(
        MergePartyWrite write,
        CancellationToken cancellationToken);
}

public sealed record EditPartyIdentityCommand(
    Guid PartyPublicId,
    long ExpectedVersion,
    string LegalName,
    string? DisplayName,
    string OperationKey);

public sealed record CreatePartyContactCommand(
    Guid PartyPublicId,
    string Name,
    string? Title,
    string? Purpose,
    string OperationKey);

public sealed record ChangePartyContactStateCommand(
    Guid PartyPublicId,
    Guid ContactPublicId,
    long ExpectedVersion,
    string State,
    string OperationKey);

public sealed record CreatePartyCommunicationPointCommand(
    Guid PartyPublicId,
    Guid ContactPublicId,
    string Type,
    string Value,
    string? Purpose,
    bool IsPrimary,
    string OperationKey);

public sealed record ChangePartyCommunicationPointStateCommand(
    Guid PartyPublicId,
    Guid ContactPublicId,
    Guid CommunicationPointPublicId,
    long ExpectedVersion,
    string State,
    string OperationKey);

public sealed record CreatePartyAddressCommand(
    Guid PartyPublicId,
    string Purpose,
    string Country,
    string? City,
    string? District,
    string? PostalCode,
    string? Line1,
    string? Line2,
    string? Label,
    bool IsDefault,
    string OperationKey);

public sealed record UpdatePartyAddressCommand(
    Guid PartyPublicId,
    Guid AddressPublicId,
    long ExpectedVersion,
    string Purpose,
    string Country,
    string? City,
    string? District,
    string? PostalCode,
    string? Line1,
    string? Line2,
    string? Label,
    bool IsDefault,
    string OperationKey);

public sealed record ChangePartyAddressStateCommand(
    Guid PartyPublicId,
    Guid AddressPublicId,
    long ExpectedVersion,
    string State,
    string OperationKey);

public sealed record ChangePartyTaxIdentityStateCommand(
    Guid PartyPublicId,
    Guid TaxIdentityPublicId,
    long ExpectedVersion,
    string State,
    string OperationKey);

public sealed record CreatePartyExternalMappingCommand(
    Guid PartyPublicId,
    string SystemCode,
    string? AccountScope,
    string ExternalIdentity,
    string OperationKey);

public sealed record ChangePartyExternalMappingStateCommand(
    Guid PartyPublicId,
    Guid ExternalMappingPublicId,
    long ExpectedVersion,
    string State,
    string OperationKey);

public sealed record MergePartyCommand(
    Guid SourcePartyPublicId,
    Guid SurvivorPartyPublicId,
    long SourceExpectedVersion,
    long SurvivorExpectedVersion,
    bool UseSourceIdentity,
    bool MoveSourceRoles,
    bool MoveSourceContacts,
    bool MoveSourceAddresses,
    bool MoveSourceTaxIdentities,
    bool MoveSourceExternalMappings,
    string Reason,
    string OperationKey);

public sealed class PartyMasterCommandHandler(
    IPermissionEvaluator permissionEvaluator,
    IPartyMasterMutationPersistence persistence)
{
    private const int MaxOperationKeyLength = 200;
    private const int MaxReasonLength = 1000;

    public async Task<Result<PartyMasterMutationReceipt>> EditIdentityAsync(
        EditPartyIdentityCommand command,
        IExecutionContext executionContext,
        CancellationToken cancellationToken)
    {
        if (!await AuthorizedAsync(PartyPermissions.Edit, executionContext, cancellationToken))
        {
            return Denied("edit Party identity");
        }

        if (command.PartyPublicId == Guid.Empty)
        {
            return Validation("parties.edit.party_required", "Party public id is required.");
        }

        if (command.ExpectedVersion <= 0)
        {
            return Validation("parties.edit.version_invalid", "Expected Party version must be positive.");
        }

        var legalName = RequiredTrimmed(command.LegalName);
        if (legalName is null)
        {
            return Validation("parties.legal_name_required", "Legal name is required and cannot contain surrounding whitespace.");
        }

        var displayName = OptionalTrimmed(command.DisplayName, out var displayValid);
        if (!displayValid)
        {
            return Validation("parties.display_name_whitespace", "Display name cannot contain leading or trailing whitespace.");
        }

        var contextResult = CreateContext(
            "parties.edit",
            command.OperationKey,
            "PartyIdentityChanged",
            "Party",
            command.PartyPublicId,
            null,
            executionContext);
        if (contextResult.Error is not null) return Result<PartyMasterMutationReceipt>.Failure(contextResult.Error);

        var result = await persistence.EditIdentityAsync(
            new EditPartyIdentityWrite(
                command.PartyPublicId,
                command.ExpectedVersion,
                legalName,
                displayName,
                contextResult.Context!),
            cancellationToken);

        return MapMutation(result, "Party", executionContext.CorrelationId.Value, "parties.edit");
    }

    public async Task<Result<PartyMasterMutationReceipt>> CreateContactAsync(
        CreatePartyContactCommand command,
        IExecutionContext executionContext,
        CancellationToken cancellationToken)
    {
        if (!await AuthorizedAsync(PartyPermissions.ContactManage, executionContext, cancellationToken))
        {
            return Denied("manage Party contacts");
        }

        if (command.PartyPublicId == Guid.Empty)
        {
            return Validation("parties.contact.party_required", "Party public id is required.");
        }

        var name = RequiredTrimmed(command.Name);
        if (name is null)
        {
            return Validation("parties.contact.name_required", "Contact name is required and cannot contain surrounding whitespace.");
        }

        var title = OptionalTrimmed(command.Title, out var titleValid);
        var purpose = OptionalTrimmed(command.Purpose, out var purposeValid);
        if (!titleValid || !purposeValid)
        {
            return Validation("parties.contact.text_invalid", "Contact optional text cannot contain surrounding whitespace.");
        }

        var publicId = Guid.NewGuid();
        var now = DateTimeOffset.UtcNow;
        var contextResult = CreateContext(
            "parties.contact.create",
            command.OperationKey,
            "PartyContactCreated",
            "PartyContact",
            publicId,
            null,
            executionContext,
            now);
        if (contextResult.Error is not null) return Result<PartyMasterMutationReceipt>.Failure(contextResult.Error);

        var contact = PartyContact.Create(
            publicId,
            command.PartyPublicId,
            executionContext.CompanyId,
            name,
            title,
            purpose,
            now);

        var result = await persistence.CreateContactAsync(
            new CreatePartyContactWrite(contact, contextResult.Context!),
            cancellationToken);

        return MapMutation(result, "PartyContact", executionContext.CorrelationId.Value, "parties.contact");
    }

    public async Task<Result<PartyMasterMutationReceipt>> ChangeContactStateAsync(
        ChangePartyContactStateCommand command,
        IExecutionContext executionContext,
        CancellationToken cancellationToken)
    {
        if (!await AuthorizedAsync(PartyPermissions.ContactManage, executionContext, cancellationToken))
        {
            return Denied("manage Party contacts");
        }

        if (!ValidIds(command.PartyPublicId, command.ContactPublicId))
        {
            return Validation("parties.contact.identity_required", "Party and Contact public ids are required.");
        }

        var state = ParseMasterState(command.State);
        if (state is null)
        {
            return Validation("parties.contact.state_invalid", "Contact state must be ACTIVE or INACTIVE.");
        }

        if (command.ExpectedVersion <= 0)
        {
            return Validation("parties.contact.version_invalid", "Expected Contact version must be positive.");
        }

        var action = state == PartyMasterRecordState.Active ? "PartyContactReactivated" : "PartyContactDeactivated";
        var contextResult = CreateContext(
            "parties.contact.state",
            command.OperationKey,
            action,
            "PartyContact",
            command.ContactPublicId,
            null,
            executionContext);
        if (contextResult.Error is not null) return Result<PartyMasterMutationReceipt>.Failure(contextResult.Error);

        var result = await persistence.ChangeContactStateAsync(
            new ChangePartyContactStateWrite(
                command.PartyPublicId,
                command.ContactPublicId,
                command.ExpectedVersion,
                state.Value,
                contextResult.Context!),
            cancellationToken);

        return MapMutation(result, "PartyContact", executionContext.CorrelationId.Value, "parties.contact");
    }

    public async Task<Result<PartyMasterMutationReceipt>> CreateCommunicationPointAsync(
        CreatePartyCommunicationPointCommand command,
        IExecutionContext executionContext,
        CancellationToken cancellationToken)
    {
        if (!await AuthorizedAsync(PartyPermissions.ContactManage, executionContext, cancellationToken))
        {
            return Denied("manage Party communication points");
        }

        if (!ValidIds(command.PartyPublicId, command.ContactPublicId))
        {
            return Validation("parties.communication.identity_required", "Party and Contact public ids are required.");
        }

        var type = RequiredTrimmed(command.Type);
        var value = RequiredTrimmed(command.Value);
        var purpose = OptionalTrimmed(command.Purpose, out var purposeValid);
        if (type is null || value is null || !purposeValid)
        {
            return Validation("parties.communication.value_invalid", "Communication type/value are required and text cannot contain surrounding whitespace.");
        }

        var publicId = Guid.NewGuid();
        var now = DateTimeOffset.UtcNow;
        var contextResult = CreateContext(
            "parties.communication.create",
            command.OperationKey,
            "PartyCommunicationPointCreated",
            "PartyCommunicationPoint",
            publicId,
            null,
            executionContext,
            now);
        if (contextResult.Error is not null) return Result<PartyMasterMutationReceipt>.Failure(contextResult.Error);

        var communication = PartyCommunicationPoint.Create(
            publicId,
            command.PartyPublicId,
            command.ContactPublicId,
            executionContext.CompanyId,
            type,
            value,
            purpose,
            command.IsPrimary,
            now);

        var result = await persistence.CreateCommunicationPointAsync(
            new CreatePartyCommunicationPointWrite(communication, contextResult.Context!),
            cancellationToken);

        return MapMutation(result, "PartyCommunicationPoint", executionContext.CorrelationId.Value, "parties.communication");
    }

    public async Task<Result<PartyMasterMutationReceipt>> ChangeCommunicationPointStateAsync(
        ChangePartyCommunicationPointStateCommand command,
        IExecutionContext executionContext,
        CancellationToken cancellationToken)
    {
        if (!await AuthorizedAsync(PartyPermissions.ContactManage, executionContext, cancellationToken))
        {
            return Denied("manage Party communication points");
        }

        if (!ValidIds(command.PartyPublicId, command.ContactPublicId, command.CommunicationPointPublicId))
        {
            return Validation("parties.communication.identity_required", "Party, Contact and Communication Point public ids are required.");
        }

        var state = ParseMasterState(command.State);
        if (state is null)
        {
            return Validation("parties.communication.state_invalid", "Communication Point state must be ACTIVE or INACTIVE.");
        }

        if (command.ExpectedVersion <= 0)
        {
            return Validation("parties.communication.version_invalid", "Expected Communication Point version must be positive.");
        }

        var action = state == PartyMasterRecordState.Active
            ? "PartyCommunicationPointReactivated"
            : "PartyCommunicationPointDeactivated";
        var contextResult = CreateContext(
            "parties.communication.state",
            command.OperationKey,
            action,
            "PartyCommunicationPoint",
            command.CommunicationPointPublicId,
            null,
            executionContext);
        if (contextResult.Error is not null) return Result<PartyMasterMutationReceipt>.Failure(contextResult.Error);

        var result = await persistence.ChangeCommunicationPointStateAsync(
            new ChangePartyCommunicationPointStateWrite(
                command.PartyPublicId,
                command.ContactPublicId,
                command.CommunicationPointPublicId,
                command.ExpectedVersion,
                state.Value,
                contextResult.Context!),
            cancellationToken);

        return MapMutation(result, "PartyCommunicationPoint", executionContext.CorrelationId.Value, "parties.communication");
    }

    public async Task<Result<PartyMasterMutationReceipt>> CreateAddressAsync(
        CreatePartyAddressCommand command,
        IExecutionContext executionContext,
        CancellationToken cancellationToken)
    {
        if (!await AuthorizedAsync(PartyPermissions.AddressManage, executionContext, cancellationToken))
        {
            return Denied("manage Party addresses");
        }

        if (command.PartyPublicId == Guid.Empty)
        {
            return Validation("parties.address.party_required", "Party public id is required.");
        }

        var purpose = ParseAddressPurpose(command.Purpose);
        var country = RequiredTrimmed(command.Country);
        if (purpose is null || country is null)
        {
            return Validation("parties.address.invalid", "Address purpose and country are required.");
        }

        if (!TryAddressText(command, out var normalized))
        {
            return Validation("parties.address.text_invalid", "Address text cannot contain surrounding whitespace.");
        }

        var publicId = Guid.NewGuid();
        var now = DateTimeOffset.UtcNow;
        var contextResult = CreateContext(
            "parties.address.create",
            command.OperationKey,
            "PartyAddressCreated",
            "PartyAddress",
            publicId,
            null,
            executionContext,
            now);
        if (contextResult.Error is not null) return Result<PartyMasterMutationReceipt>.Failure(contextResult.Error);

        var address = PartyAddress.Create(
            publicId,
            command.PartyPublicId,
            executionContext.CompanyId,
            purpose.Value,
            country,
            normalized.City,
            normalized.District,
            normalized.PostalCode,
            normalized.Line1,
            normalized.Line2,
            normalized.Label,
            command.IsDefault,
            now);

        var result = await persistence.CreateAddressAsync(
            new CreatePartyAddressWrite(address, contextResult.Context!),
            cancellationToken);

        return MapMutation(result, "PartyAddress", executionContext.CorrelationId.Value, "parties.address");
    }

    public async Task<Result<PartyMasterMutationReceipt>> UpdateAddressAsync(
        UpdatePartyAddressCommand command,
        IExecutionContext executionContext,
        CancellationToken cancellationToken)
    {
        if (!await AuthorizedAsync(PartyPermissions.AddressManage, executionContext, cancellationToken))
        {
            return Denied("manage Party addresses");
        }

        if (!ValidIds(command.PartyPublicId, command.AddressPublicId))
        {
            return Validation("parties.address.identity_required", "Party and Address public ids are required.");
        }

        if (command.ExpectedVersion <= 0)
        {
            return Validation("parties.address.version_invalid", "Expected Address version must be positive.");
        }

        var purpose = ParseAddressPurpose(command.Purpose);
        var country = RequiredTrimmed(command.Country);
        if (purpose is null || country is null)
        {
            return Validation("parties.address.invalid", "Address purpose and country are required.");
        }

        if (!TryAddressText(command, out var normalized))
        {
            return Validation("parties.address.text_invalid", "Address text cannot contain surrounding whitespace.");
        }

        var contextResult = CreateContext(
            "parties.address.update",
            command.OperationKey,
            "PartyAddressChanged",
            "PartyAddress",
            command.AddressPublicId,
            null,
            executionContext);
        if (contextResult.Error is not null) return Result<PartyMasterMutationReceipt>.Failure(contextResult.Error);

        var result = await persistence.UpdateAddressAsync(
            new UpdatePartyAddressWrite(
                command.PartyPublicId,
                command.AddressPublicId,
                command.ExpectedVersion,
                purpose.Value,
                country,
                normalized.City,
                normalized.District,
                normalized.PostalCode,
                normalized.Line1,
                normalized.Line2,
                normalized.Label,
                command.IsDefault,
                contextResult.Context!),
            cancellationToken);

        return MapMutation(result, "PartyAddress", executionContext.CorrelationId.Value, "parties.address");
    }

    public async Task<Result<PartyMasterMutationReceipt>> ChangeAddressStateAsync(
        ChangePartyAddressStateCommand command,
        IExecutionContext executionContext,
        CancellationToken cancellationToken)
    {
        if (!await AuthorizedAsync(PartyPermissions.AddressManage, executionContext, cancellationToken))
        {
            return Denied("manage Party addresses");
        }

        if (!ValidIds(command.PartyPublicId, command.AddressPublicId))
        {
            return Validation("parties.address.identity_required", "Party and Address public ids are required.");
        }

        var state = ParseMasterState(command.State);
        if (state is null)
        {
            return Validation("parties.address.state_invalid", "Address state must be ACTIVE or INACTIVE.");
        }

        if (command.ExpectedVersion <= 0)
        {
            return Validation("parties.address.version_invalid", "Expected Address version must be positive.");
        }

        var action = state == PartyMasterRecordState.Active ? "PartyAddressReactivated" : "PartyAddressDeactivated";
        var contextResult = CreateContext(
            "parties.address.state",
            command.OperationKey,
            action,
            "PartyAddress",
            command.AddressPublicId,
            null,
            executionContext);
        if (contextResult.Error is not null) return Result<PartyMasterMutationReceipt>.Failure(contextResult.Error);

        var result = await persistence.ChangeAddressStateAsync(
            new ChangePartyAddressStateWrite(
                command.PartyPublicId,
                command.AddressPublicId,
                command.ExpectedVersion,
                state.Value,
                contextResult.Context!),
            cancellationToken);

        return MapMutation(result, "PartyAddress", executionContext.CorrelationId.Value, "parties.address");
    }

    public async Task<Result<PartyMasterMutationReceipt>> ChangeTaxIdentityStateAsync(
        ChangePartyTaxIdentityStateCommand command,
        IExecutionContext executionContext,
        CancellationToken cancellationToken)
    {
        if (!await AuthorizedAsync(PartyPermissions.TaxIdentityManage, executionContext, cancellationToken))
        {
            return Denied("manage Party tax identities");
        }

        if (!ValidIds(command.PartyPublicId, command.TaxIdentityPublicId))
        {
            return Validation("parties.tax_identity.identity_required", "Party and Tax Identity public ids are required.");
        }

        if (command.ExpectedVersion <= 0)
        {
            return Validation("parties.tax_identity.version_invalid", "Expected Tax Identity version must be positive.");
        }

        var target = command.State switch
        {
            "ACTIVE" => PartyTaxIdentityState.Active,
            "INACTIVE" => PartyTaxIdentityState.Inactive,
            _ => (PartyTaxIdentityState?)null
        };
        if (target is null)
        {
            return Validation("parties.tax_identity.state_invalid", "Tax Identity state must be ACTIVE or INACTIVE.");
        }

        var action = target == PartyTaxIdentityState.Active
            ? "PartyTaxIdentityReactivated"
            : "PartyTaxIdentityDeactivated";
        var contextResult = CreateContext(
            "parties.tax_identity.state",
            command.OperationKey,
            action,
            "PartyTaxIdentity",
            command.TaxIdentityPublicId,
            null,
            executionContext);
        if (contextResult.Error is not null) return Result<PartyMasterMutationReceipt>.Failure(contextResult.Error);

        var result = await persistence.ChangeTaxIdentityStateAsync(
            new ChangePartyTaxIdentityStateWrite(
                command.PartyPublicId,
                command.TaxIdentityPublicId,
                command.ExpectedVersion,
                target.Value,
                contextResult.Context!),
            cancellationToken);

        return MapMutation(result, "PartyTaxIdentity", executionContext.CorrelationId.Value, "parties.tax_identity");
    }

    public async Task<Result<PartyMasterMutationReceipt>> CreateExternalMappingAsync(
        CreatePartyExternalMappingCommand command,
        IExecutionContext executionContext,
        CancellationToken cancellationToken)
    {
        if (!await AuthorizedAsync(PartyPermissions.ExternalMappingManage, executionContext, cancellationToken))
        {
            return Denied("manage Party external mappings");
        }

        if (command.PartyPublicId == Guid.Empty)
        {
            return Validation("parties.external_mapping.party_required", "Party public id is required.");
        }

        var systemCode = RequiredTrimmed(command.SystemCode);
        var externalIdentity = RequiredTrimmed(command.ExternalIdentity);
        var accountScope = OptionalTrimmed(command.AccountScope, out var accountScopeValid);
        if (systemCode is null || externalIdentity is null || !accountScopeValid)
        {
            return Validation(
                "parties.external_mapping.invalid",
                "External mapping system/external identity are required and text cannot contain surrounding whitespace.");
        }

        var publicId = Guid.NewGuid();
        var now = DateTimeOffset.UtcNow;
        var contextResult = CreateContext(
            "parties.external_mapping.create",
            command.OperationKey,
            "PartyExternalMappingCreated",
            "PartyExternalMapping",
            publicId,
            null,
            executionContext,
            now);
        if (contextResult.Error is not null) return Result<PartyMasterMutationReceipt>.Failure(contextResult.Error);

        var mapping = PartyExternalMapping.Create(
            publicId,
            command.PartyPublicId,
            executionContext.CompanyId,
            systemCode,
            accountScope,
            externalIdentity,
            now);

        var result = await persistence.CreateExternalMappingAsync(
            new CreatePartyExternalMappingWrite(mapping, contextResult.Context!),
            cancellationToken);

        return MapMutation(result, "PartyExternalMapping", executionContext.CorrelationId.Value, "parties.external_mapping");
    }

    public async Task<Result<PartyMasterMutationReceipt>> ChangeExternalMappingStateAsync(
        ChangePartyExternalMappingStateCommand command,
        IExecutionContext executionContext,
        CancellationToken cancellationToken)
    {
        if (!await AuthorizedAsync(PartyPermissions.ExternalMappingManage, executionContext, cancellationToken))
        {
            return Denied("manage Party external mappings");
        }

        if (!ValidIds(command.PartyPublicId, command.ExternalMappingPublicId))
        {
            return Validation(
                "parties.external_mapping.identity_required",
                "Party and External Mapping public ids are required.");
        }

        var state = ParseMasterState(command.State);
        if (state is null)
        {
            return Validation(
                "parties.external_mapping.state_invalid",
                "External Mapping state must be ACTIVE or INACTIVE.");
        }

        if (command.ExpectedVersion <= 0)
        {
            return Validation(
                "parties.external_mapping.version_invalid",
                "Expected External Mapping version must be positive.");
        }

        var action = state == PartyMasterRecordState.Active
            ? "PartyExternalMappingReactivated"
            : "PartyExternalMappingDeactivated";
        var contextResult = CreateContext(
            "parties.external_mapping.state",
            command.OperationKey,
            action,
            "PartyExternalMapping",
            command.ExternalMappingPublicId,
            null,
            executionContext);
        if (contextResult.Error is not null) return Result<PartyMasterMutationReceipt>.Failure(contextResult.Error);

        var result = await persistence.ChangeExternalMappingStateAsync(
            new ChangePartyExternalMappingStateWrite(
                command.PartyPublicId,
                command.ExternalMappingPublicId,
                command.ExpectedVersion,
                state.Value,
                contextResult.Context!),
            cancellationToken);

        return MapMutation(result, "PartyExternalMapping", executionContext.CorrelationId.Value, "parties.external_mapping");
    }

    public async Task<Result<PartyMergeReceipt>> MergeAsync(
        MergePartyCommand command,
        IExecutionContext executionContext,
        CancellationToken cancellationToken)
    {
        if (!await AuthorizedAsync(PartyPermissions.Merge, executionContext, cancellationToken))
        {
            return Result<PartyMergeReceipt>.Failure(PermissionDenied("merge Parties"));
        }

        if (!ValidIds(command.SourcePartyPublicId, command.SurvivorPartyPublicId))
        {
            return MergeValidation("parties.merge.identity_required", "Source and survivor Party public ids are required.");
        }

        if (command.SourcePartyPublicId == command.SurvivorPartyPublicId)
        {
            return MergeValidation("parties.merge.same_party", "Source and survivor Party must be different.");
        }

        if (command.SourceExpectedVersion <= 0 || command.SurvivorExpectedVersion <= 0)
        {
            return MergeValidation("parties.merge.version_invalid", "Source and survivor expected versions must be positive.");
        }

        var reason = command.Reason?.Trim() ?? string.Empty;
        if (reason.Length == 0)
        {
            return MergeValidation("parties.merge.reason_required", "A merge reason is required.");
        }

        if (reason.Length > MaxReasonLength)
        {
            return MergeValidation("parties.merge.reason_too_long", $"Merge reason cannot exceed {MaxReasonLength} characters.");
        }

        var lineagePublicId = Guid.NewGuid();
        var now = DateTimeOffset.UtcNow;
        var contextResult = CreateContext(
            "parties.merge",
            command.OperationKey,
            "PartyMerged",
            "Party",
            command.SourcePartyPublicId,
            reason,
            executionContext,
            now);
        if (contextResult.Error is not null) return Result<PartyMergeReceipt>.Failure(contextResult.Error);

        var lineage = PartyMergeLineage.Create(
            lineagePublicId,
            executionContext.CompanyId,
            command.SourcePartyPublicId,
            command.SurvivorPartyPublicId,
            executionContext.ActorId,
            reason,
            now);

        var result = await persistence.MergeAsync(
            new MergePartyWrite(
                command.SourcePartyPublicId,
                command.SurvivorPartyPublicId,
                command.SourceExpectedVersion,
                command.SurvivorExpectedVersion,
                command.UseSourceIdentity,
                command.MoveSourceRoles,
                command.MoveSourceContacts,
                command.MoveSourceAddresses,
                command.MoveSourceTaxIdentities,
                command.MoveSourceExternalMappings,
                lineage,
                contextResult.Context!),
            cancellationToken);

        if (result.Outcome != PartyMasterMutationOutcome.Succeeded)
        {
            return Result<PartyMergeReceipt>.Failure(MapError(result.Outcome, "parties.merge"));
        }

        return Result<PartyMergeReceipt>.Success(new PartyMergeReceipt(
            command.SourcePartyPublicId,
            command.SurvivorPartyPublicId,
            result.State ?? "MERGED",
            result.Version ?? throw new InvalidOperationException("Merge did not return source version."),
            result.RelatedVersion ?? throw new InvalidOperationException("Merge did not return survivor version."),
            executionContext.CorrelationId.Value));
    }

    private async Task<bool> AuthorizedAsync(
        string permission,
        IExecutionContext executionContext,
        CancellationToken cancellationToken)
    {
        ArgumentNullException.ThrowIfNull(executionContext);
        cancellationToken.ThrowIfCancellationRequested();
        return await permissionEvaluator.IsGrantedAsync(
            executionContext.ActorId,
            executionContext.CompanyId,
            permission,
            cancellationToken);
    }

    private static Result<PartyMasterMutationReceipt> MapMutation(
        PartyMasterMutationPersistenceResult result,
        string entityType,
        string correlationId,
        string codePrefix)
    {
        if (result.Outcome != PartyMasterMutationOutcome.Succeeded)
        {
            return Result<PartyMasterMutationReceipt>.Failure(MapError(result.Outcome, codePrefix));
        }

        return Result<PartyMasterMutationReceipt>.Success(new PartyMasterMutationReceipt(
            result.EntityPublicId ?? throw new InvalidOperationException("Mutation did not return entity public id."),
            entityType,
            result.State ?? "ACTIVE",
            result.Version ?? throw new InvalidOperationException("Mutation did not return entity version."),
            correlationId));
    }

    private static ApplicationError MapError(
        PartyMasterMutationOutcome outcome,
        string codePrefix) =>
        outcome switch
        {
            PartyMasterMutationOutcome.PartyNotFound => new(
                ErrorCategory.NotFound,
                "parties.party_not_found",
                "Party was not found in the current company."),
            PartyMasterMutationOutcome.ChildNotFound => new(
                ErrorCategory.NotFound,
                $"{codePrefix}.not_found",
                "The requested Party master record was not found."),
            PartyMasterMutationOutcome.DuplicateOperation => new(
                ErrorCategory.Conflict,
                $"{codePrefix}.duplicate_operation",
                "The idempotency key has already been used for this Party operation in the current company."),
            PartyMasterMutationOutcome.StaleVersion => new(
                ErrorCategory.Concurrency,
                $"{codePrefix}.stale_version",
                "The Party master record changed after the supplied version was read."),
            PartyMasterMutationOutcome.StateConflict => new(
                ErrorCategory.Conflict,
                $"{codePrefix}.state_conflict",
                "The Party master record is already in, or cannot enter, the requested state."),
            PartyMasterMutationOutcome.DeterministicConflict => new(
                ErrorCategory.Conflict,
                $"{codePrefix}.deterministic_conflict",
                "A deterministic Party master uniqueness or merge conflict must be resolved before this operation can complete."),
            PartyMasterMutationOutcome.InvalidMerge => new(
                ErrorCategory.Conflict,
                "parties.merge.invalid",
                "The selected source/survivor Party state or merge lineage does not allow this merge."),
            _ => new(
                ErrorCategory.Infrastructure,
                $"{codePrefix}.unexpected",
                "The Party operation returned an unexpected persistence result.")
        };

    private static (PartyMasterWriteContext? Context, ApplicationError? Error) CreateContext(
        string scopeName,
        string operationKey,
        string action,
        string entityType,
        Guid entityPublicId,
        string? reason,
        IExecutionContext executionContext,
        DateTimeOffset? occurredAt = null)
    {
        var key = operationKey ?? string.Empty;
        if (string.IsNullOrWhiteSpace(key))
        {
            return (null, new ApplicationError(
                ErrorCategory.Validation,
                $"{scopeName}.idempotency_key_required",
                "Idempotency-Key is required."));
        }

        if (!string.Equals(key, key.Trim(), StringComparison.Ordinal) || key.Length > MaxOperationKeyLength)
        {
            return (null, new ApplicationError(
                ErrorCategory.Validation,
                $"{scopeName}.idempotency_key_invalid",
                $"Idempotency-Key must be trimmed and cannot exceed {MaxOperationKeyLength} characters."));
        }

        var now = occurredAt ?? DateTimeOffset.UtcNow;
        return (
            new PartyMasterWriteContext(
                executionContext.CompanyId,
                new IdempotencyOperation(
                    $"{scopeName}:{executionContext.CompanyId:D}",
                    key,
                    requestFingerprint: null,
                    now),
                new AuditEntry(
                    executionContext.ActorId,
                    executionContext.CompanyId,
                    executionContext.BranchId,
                    executionContext.CorrelationId.Value,
                    "Parties",
                    action,
                    entityType,
                    entityPublicId,
                    reason,
                    now)),
            null);
    }

    private static Result<PartyMasterMutationReceipt> Validation(string code, string message) =>
        Result<PartyMasterMutationReceipt>.Failure(
            new ApplicationError(ErrorCategory.Validation, code, message));

    private static Result<PartyMasterMutationReceipt> Denied(string action) =>
        Result<PartyMasterMutationReceipt>.Failure(PermissionDenied(action));

    private static Result<PartyMergeReceipt> MergeValidation(string code, string message) =>
        Result<PartyMergeReceipt>.Failure(
            new ApplicationError(ErrorCategory.Validation, code, message));

    private static ApplicationError PermissionDenied(string action) =>
        new(
            ErrorCategory.Authorization,
            "authorization.permission_denied",
            $"The current actor is not authorized to {action}.");

    private static PartyMasterRecordState? ParseMasterState(string state) =>
        state switch
        {
            "ACTIVE" => PartyMasterRecordState.Active,
            "INACTIVE" => PartyMasterRecordState.Inactive,
            _ => null
        };

    private static PartyAddressPurpose? ParseAddressPurpose(string purpose) =>
        purpose switch
        {
            "BILLING" => PartyAddressPurpose.Billing,
            "SHIPPING" => PartyAddressPurpose.Shipping,
            "GENERAL" => PartyAddressPurpose.General,
            _ => null
        };

    private static string? RequiredTrimmed(string? value)
    {
        if (string.IsNullOrWhiteSpace(value)) return null;
        return string.Equals(value, value.Trim(), StringComparison.Ordinal) ? value : null;
    }

    private static string? OptionalTrimmed(string? value, out bool valid)
    {
        if (string.IsNullOrWhiteSpace(value))
        {
            valid = true;
            return null;
        }

        valid = string.Equals(value, value.Trim(), StringComparison.Ordinal);
        return valid ? value : null;
    }

    private static bool ValidIds(params Guid[] ids) => ids.All(x => x != Guid.Empty);

    private static bool TryAddressText(CreatePartyAddressCommand command, out AddressText normalized)
    {
        return TryAddressText(
            command.City,
            command.District,
            command.PostalCode,
            command.Line1,
            command.Line2,
            command.Label,
            out normalized);
    }

    private static bool TryAddressText(UpdatePartyAddressCommand command, out AddressText normalized)
    {
        return TryAddressText(
            command.City,
            command.District,
            command.PostalCode,
            command.Line1,
            command.Line2,
            command.Label,
            out normalized);
    }

    private static bool TryAddressText(
        string? city,
        string? district,
        string? postalCode,
        string? line1,
        string? line2,
        string? label,
        out AddressText normalized)
    {
        var values = new[] { city, district, postalCode, line1, line2, label };
        if (values.Any(value =>
                !string.IsNullOrWhiteSpace(value) &&
                !string.Equals(value, value.Trim(), StringComparison.Ordinal)))
        {
            normalized = default;
            return false;
        }

        normalized = new AddressText(
            Normalize(city),
            Normalize(district),
            Normalize(postalCode),
            Normalize(line1),
            Normalize(line2),
            Normalize(label));
        return true;
    }

    private static string? Normalize(string? value) =>
        string.IsNullOrWhiteSpace(value) ? null : value.Trim();

    private readonly record struct AddressText(
        string? City,
        string? District,
        string? PostalCode,
        string? Line1,
        string? Line2,
        string? Label);
}
